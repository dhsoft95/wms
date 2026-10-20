<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\ContainerStatus;
use App\Enums\Inventory\MovementType;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\PutawayTaskStatus;
use App\Enums\TransferStatus;
use App\Models\CycleCountLine;
use App\Models\DeliveryOrder;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\GrnVarianceApproval;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderApproval;
use App\Models\PutawayTask;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Services\Inbound\ContainerQueryService;
use App\Services\Inventory\StockQueryService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class DashboardMetrics
{
    private const TTL = 300; // 5 minutes

    public function __construct(
        private readonly StockQueryService    $stockQuery,
        private readonly ContainerQueryService $containerQuery,
    ) {}

    // ── KPI methods ───────────────────────────────────────────────────────────

    public function stockOnHand(User $user, ?int $warehouseId = null): array
    {
        return $this->remember('stock_on_hand', $user, $warehouseId, function () use ($user, $warehouseId) {
            $base = StockBalance::where('qty_on_hand', '>', 0);
            $this->applyVwScope($base, $user);
            $this->applyWarehouseScope($base, $warehouseId);

            return [
                'qty'       => (float) (clone $base)->sum('qty_on_hand'),
                'sku_count' => (clone $base)->distinct('item_id')->count('item_id'),
            ];
        });
    }

    public function openOrders(User $user, ?int $warehouseId = null): array
    {
        return $this->remember('open_orders', $user, $warehouseId, function () use ($user, $warehouseId) {
            $closedPoStatuses = ['verified_closed', 'cancelled'];
            $poQuery = PurchaseOrder::whereNotIn('status', $closedPoStatuses);
            if ($warehouseId) {
                $poQuery->where('warehouse_id', $warehouseId);
            }

            $grnQuery = Grn::whereIn('status', ['draft', 'receiving', 'pending_variance_approval', 'received', 'putaway_in_progress', 'putaway_complete']);
            if ($warehouseId) {
                $grnQuery->where('warehouse_id', $warehouseId);
            }

            $transferQuery = StockTransfer::whereIn('status', [TransferStatus::Draft->value, TransferStatus::Submitted->value, TransferStatus::Accepted->value]);

            return [
                'po_count'       => $poQuery->count(),
                'grn_count'      => $grnQuery->count(),
                'transfer_count' => $transferQuery->count(),
                'total'          => $poQuery->count() + $grnQuery->count() + $transferQuery->count(),
            ];
        });
    }

    public function pendingApprovals(User $user): array
    {
        return $this->remember('pending_approvals', $user, null, function () use ($user) {
            $poCount  = PurchaseOrderApproval::where('user_id', $user->id)->whereNull('action')->count();
            $grnCount = GrnVarianceApproval::where('decided_by', null)->whereHas('grn', fn ($q) => $q->where('warehouse_id', '>', 0))->count();

            return [
                'po_count'  => $poCount,
                'grn_count' => $grnCount,
                'total'     => $poCount + $grnCount,
            ];
        });
    }

    public function toReceive(User $user, ?int $warehouseId = null): array
    {
        return $this->remember('to_receive', $user, $warehouseId, function () use ($user, $warehouseId) {
            $query = Grn::whereIn('status', ['draft', 'receiving']);
            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            $todayQuery = (clone $query)->whereDate('created_at', today());

            return [
                'total' => $query->count(),
                'today' => $todayQuery->count(),
            ];
        });
    }

    public function awaitingPutaway(User $user, ?int $warehouseId = null): array
    {
        return $this->remember('awaiting_putaway', $user, $warehouseId, function () use ($user, $warehouseId) {
            $query = PutawayTask::whereIn('status', [
                PutawayTaskStatus::Open->value,
                PutawayTaskStatus::Assigned->value,
                PutawayTaskStatus::InProgress->value,
            ]);
            if ($warehouseId) {
                $query->whereHas('grn', fn ($q) => $q->where('warehouse_id', $warehouseId));
            }

            return ['total' => $query->count()];
        });
    }

    public function toPick(User $user, ?int $warehouseId = null): array
    {
        return $this->remember('to_pick', $user, $warehouseId, function () use ($user, $warehouseId) {
            $query = DeliveryOrder::whereIn('status', [
                DeliveryOrderStatus::Approved->value,
                DeliveryOrderStatus::Allocated->value,
                DeliveryOrderStatus::PartiallyAllocated->value,
                DeliveryOrderStatus::Picking->value,
            ]);
            if ($warehouseId) {
                $query->whereHas('lines', fn ($q) => $q->where('warehouse_id', $warehouseId));
            }
            if (! $user->canAccessAllVirtualWarehouses()) {
                $vwIds = $user->accessibleVirtualWarehouseIds();
                if (! empty($vwIds)) {
                    $query->whereHas('lines', fn ($q) => $q->whereIn('virtual_warehouse_id', $vwIds));
                }
            }

            return ['total' => $query->count()];
        });
    }

    public function inTransit(User $user, ?int $warehouseId = null): array
    {
        return $this->remember('in_transit', $user, $warehouseId, function () use ($user, $warehouseId) {
            $containers = $this->containerQuery->queryFor($user)
                ->whereIn('status', [ContainerStatus::Expected->value, ContainerStatus::Arrived->value])
                ->count();

            $transfers = StockTransfer::where('status', TransferStatus::Accepted->value)->count();

            return [
                'containers' => $containers,
                'transfers'  => $transfers,
                'total'      => $containers + $transfers,
            ];
        });
    }

    public function lowStock(User $user, ?int $warehouseId = null): array
    {
        return $this->remember('low_stock', $user, $warehouseId, function () use ($user, $warehouseId) {
            $stats = $this->stockQuery->stockStats($user);
            return ['count' => $stats['below_min']];
        });
    }

    public function expiring(User $user, ?int $warehouseId = null): array
    {
        return $this->remember('expiring', $user, $warehouseId, function () use ($user, $warehouseId) {
            $cutoff = now()->addDays(30)->toDateString();
            $query  = StockBalance::where('qty_on_hand', '>', 0)
                ->whereHas('batch', fn ($q) => $q->whereNotNull('expiry_date')->where('expiry_date', '<=', $cutoff));
            $this->applyVwScope($query, $user);
            $this->applyWarehouseScope($query, $warehouseId);

            return ['count' => $query->distinct('item_id')->count('item_id')];
        });
    }

    public function containersInYard(User $user): array
    {
        return $this->remember('containers_in_yard', $user, null, function () use ($user) {
            $activeValues = array_column(
                array_filter(ContainerStatus::cases(), fn ($s) => $s->isActive()),
                'value'
            );

            $total     = $this->containerQuery->queryFor($user)->whereIn('status', $activeValues)->count();
            $overDwell = $this->containerQuery->queryFor($user)
                ->whereIn('status', [ContainerStatus::Parked->value, ContainerStatus::Unloaded->value])
                ->where('eta', '<', now()->subDays(7))
                ->count();

            return ['total' => $total, 'over_dwell' => $overDwell];
        });
    }

    // ── Action lists ──────────────────────────────────────────────────────────

    public function myApprovals(User $user): Collection
    {
        return Cache::remember(
            "dashboard.my_approvals.u{$user->id}",
            self::TTL,
            function () use ($user) {
                $poApprovals = PurchaseOrderApproval::with(['purchaseOrder'])
                    ->where('user_id', $user->id)
                    ->whereNull('action')
                    ->latest()
                    ->limit(10)
                    ->get()
                    ->map(fn ($a) => [
                        'type'   => 'PO Approval',
                        'ref'    => $a->purchaseOrder?->number ?? '—',
                        'desc'   => 'Level ' . $a->level . ' approval required',
                        'age'    => $a->created_at->diffForHumans(),
                        'badge'  => 'badge-slate',
                        'model'  => $a,
                    ]);

                $grnApprovals = GrnVarianceApproval::with(['grn', 'requestedBy'])
                    ->whereNull('decided_by')
                    ->whereNull('decision')
                    ->latest()
                    ->limit(10)
                    ->get()
                    ->map(fn ($a) => [
                        'type'  => 'GRN Variance',
                        'ref'   => $a->grn?->number ?? '—',
                        'desc'  => 'Variance approval — ' . ($a->requestedBy?->name ?? '?'),
                        'age'   => $a->created_at->diffForHumans(),
                        'badge' => 'badge-amber',
                        'model' => $a,
                    ]);

                return $poApprovals->merge($grnApprovals)->sortByDesc(fn ($a) => $a['model']->created_at)->take(15)->values();
            }
        );
    }

    public function overdueOrders(User $user, ?int $warehouseId = null): Collection
    {
        return Cache::remember(
            "dashboard.overdue_orders.u{$user->id}.w" . ($warehouseId ?? 0),
            self::TTL,
            function () use ($user, $warehouseId) {
                $query = DeliveryOrder::with(['consigneePartner'])
                    ->whereNotNull('required_date')
                    ->whereDate('required_date', '<', today())
                    ->whereNotIn('status', [
                        DeliveryOrderStatus::Dispatched->value,
                        DeliveryOrderStatus::Delivered->value,
                        DeliveryOrderStatus::Cancelled->value,
                    ]);

                if ($warehouseId) {
                    $query->whereHas('lines', fn ($q) => $q->where('warehouse_id', $warehouseId));
                }

                return $query->latest('required_date')->limit(10)->get();
            }
        );
    }

    public function recentVariances(User $user, ?int $warehouseId = null): Collection
    {
        return Cache::remember(
            "dashboard.recent_variances.u{$user->id}.w" . ($warehouseId ?? 0),
            self::TTL,
            function () use ($user, $warehouseId) {
                $grnLines = GrnLine::with(['grn', 'item'])
                    ->whereNotIn('variance_type', ['none'])
                    ->whereNotNull('variance_qty')
                    ->whereRaw('ABS(variance_qty) > 0')
                    ->when($warehouseId, fn ($q) => $q->whereHas('grn', fn ($g) => $g->where('warehouse_id', $warehouseId)))
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(fn ($l) => [
                        'source' => 'GRN',
                        'ref'    => $l->grn?->grn_number ?? '—',
                        'item'   => $l->item?->name ?? '—',
                        'sku'    => $l->item?->sku ?? '—',
                        'var'    => (float) $l->variance_qty,
                        'badge'  => $l->variance_type?->color() ?? 'badge-slate',
                        'label'  => $l->variance_type?->label() ?? '—',
                        'at'     => $l->updated_at,
                    ]);

                $ccLines = CycleCountLine::with(['item', 'count'])
                    ->whereNotNull('variance')
                    ->whereRaw('ABS(variance) > 0')
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(fn ($l) => [
                        'source' => 'Cycle Count',
                        'ref'    => $l->count?->number ?? '—',
                        'item'   => $l->item?->name ?? '—',
                        'sku'    => $l->item?->sku ?? '—',
                        'var'    => (float) $l->variance,
                        'badge'  => (float) $l->variance < 0 ? 'badge-red' : 'badge-amber',
                        'label'  => (float) $l->variance < 0 ? 'Shortage' : 'Surplus',
                        'at'     => $l->updated_at,
                    ]);

                return $grnLines->merge($ccLines)->sortByDesc('at')->take(10)->values();
            }
        );
    }

    public function recentActivity(User $user, ?int $warehouseId = null): Collection
    {
        return Cache::remember(
            "dashboard.recent_activity.u{$user->id}.w" . ($warehouseId ?? 0),
            self::TTL,
            function () use ($user, $warehouseId) {
                return StockTransaction::with(['postedBy'])
                    ->when(
                        $warehouseId,
                        fn ($q) => $q->whereHas('movements', fn ($m) =>
                            $m->whereHas('location', fn ($l) => $l->where('warehouse_id', $warehouseId))
                        )
                    )
                    ->latest('posted_at')
                    ->limit(10)
                    ->get();
            }
        );
    }

    // ── Chart data methods ───────────────────────────────────────────────────

    /** Chart 1: daily received vs dispatched qty over the chosen period. */
    public function chartInboundVsOutbound(User $user, int $days = 30): array
    {
        return $this->remember('chart_inbound_outbound', $user, $days, function () use ($user, $days) {
            $from   = now()->subDays($days - 1)->startOfDay();
            $period = CarbonPeriod::create($from, '1 day', now());
            $dates  = collect($period)->map(fn ($d) => $d->format('d M'));

            $received = StockMovement::join('stock_transactions', 'stock_movements.stock_transaction_id', '=', 'stock_transactions.id')
                ->where('stock_transactions.type', MovementType::Receipt->value)
                ->where('stock_movements.qty', '>', 0)
                ->where('stock_transactions.posted_at', '>=', $from)
                ->groupBy(DB::raw('DATE(stock_transactions.posted_at)'))
                ->select(DB::raw('DATE(stock_transactions.posted_at) as d'), DB::raw('SUM(stock_movements.qty) as total'))
                ->pluck('total', 'd');

            $dispatched = StockMovement::join('stock_transactions', 'stock_movements.stock_transaction_id', '=', 'stock_transactions.id')
                ->where('stock_transactions.type', MovementType::Dispatch->value)
                ->where('stock_movements.qty', '<', 0)
                ->where('stock_transactions.posted_at', '>=', $from)
                ->groupBy(DB::raw('DATE(stock_transactions.posted_at)'))
                ->select(DB::raw('DATE(stock_transactions.posted_at) as d'), DB::raw('ABS(SUM(stock_movements.qty)) as total'))
                ->pluck('total', 'd');

            $recvData = collect($period)->map(fn ($d) => (float) ($received[$d->format('Y-m-d')] ?? 0));
            $dispData = collect($period)->map(fn ($d) => (float) ($dispatched[$d->format('Y-m-d')] ?? 0));

            return [
                'categories' => $dates->values()->all(),
                'series'     => [
                    ['name' => 'Received',   'data' => $recvData->values()->all()],
                    ['name' => 'Dispatched', 'data' => $dispData->values()->all()],
                ],
            ];
        });
    }

    /** Chart 2: total qty on hand per virtual warehouse. */
    public function chartStockByVw(User $user): array
    {
        return $this->remember('chart_stock_vw', $user, null, function () use ($user) {
            $query = StockBalance::join('virtual_warehouses', 'stock_balances.virtual_warehouse_id', '=', 'virtual_warehouses.id')
                ->where('stock_balances.qty_on_hand', '>', 0)
                ->groupBy('virtual_warehouses.id', 'virtual_warehouses.name')
                ->select('virtual_warehouses.name', DB::raw('SUM(stock_balances.qty_on_hand) as total'));

            if (! $user->canAccessAllVirtualWarehouses()) {
                $vwIds = $user->accessibleVirtualWarehouseIds();
                if (! empty($vwIds)) {
                    $query->whereIn('stock_balances.virtual_warehouse_id', $vwIds);
                }
            }

            $rows = $query->orderByDesc('total')->limit(15)->get();

            return [
                'categories' => $rows->pluck('name')->all(),
                'series'     => [['name' => 'Stock on Hand', 'data' => $rows->map(fn ($r) => (float) $r->total)->all()]],
            ];
        });
    }

    /** Chart 3: top 10 item categories + "Other" roll-up. */
    public function chartStockByCategory(User $user): array
    {
        return $this->remember('chart_stock_category', $user, null, function () use ($user) {
            $query = StockBalance::join('items', 'stock_balances.item_id', '=', 'items.id')
                ->join('item_categories', 'items.category_id', '=', 'item_categories.id')
                ->where('stock_balances.qty_on_hand', '>', 0)
                ->groupBy('item_categories.id', 'item_categories.name')
                ->select('item_categories.name', DB::raw('SUM(stock_balances.qty_on_hand) as total'))
                ->orderByDesc('total')
                ->get();

            if ($query->isEmpty()) {
                return ['categories' => [], 'series' => []];
            }

            $top   = $query->take(10);
            $other = $query->skip(10)->sum('total');
            if ($other > 0) {
                $top->push((object) ['name' => 'Other', 'total' => $other]);
            }

            return [
                'categories' => $top->pluck('name')->all(),
                'series'     => [['name' => 'Stock on Hand', 'data' => $top->map(fn ($r) => (float) $r->total)->all()]],
            ];
        });
    }

    /** Chart 4: DO count by status (Submitted → Approved → Picking → Dispatched). */
    public function chartOrderStatusPipeline(User $user): array
    {
        return $this->remember('chart_order_pipeline', $user, null, function () use ($user) {
            $statuses = [
                DeliveryOrderStatus::Submitted->value,
                DeliveryOrderStatus::Approved->value,
                DeliveryOrderStatus::Picking->value,
                DeliveryOrderStatus::Dispatched->value,
            ];

            $counts = DeliveryOrder::whereIn('status', $statuses)
                ->groupBy('status')
                ->select('status', DB::raw('COUNT(*) as total'))
                ->pluck('total', 'status');

            $labels = ['Submitted', 'Approved', 'Picking', 'Dispatched'];
            $data   = array_map(fn ($s) => (int) ($counts[$s] ?? 0), $statuses);

            return [
                'categories' => $labels,
                'series'     => [['name' => 'Orders', 'data' => $data]],
            ];
        });
    }

    /** Chart 5: % of active bin locations that have stock (radialBar). */
    public function chartBinUtilisation(User $user, ?int $warehouseId = null): array
    {
        return $this->remember('chart_bin_util', $user, $warehouseId, function () use ($user, $warehouseId) {
            $query = Location::where('level', LocationLevel::Bin->value)
                ->where('status', LocationStatus::Active->value);

            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            $total = (clone $query)->count();
            $withStock = (clone $query)
                ->whereExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('stock_balances')
                    ->whereColumn('stock_balances.location_id', 'locations.id')
                    ->where('stock_balances.qty_on_hand', '>', 0)
                )->count();

            $pct = $total > 0 ? round(($withStock / $total) * 100, 1) : 0;

            return [
                'categories' => ['Utilised'],
                'series'     => [$pct],
            ];
        });
    }

    /** Chart 6: qty bucketed by expiry profile. */
    public function chartExpiryProfile(User $user, ?int $warehouseId = null): array
    {
        return $this->remember('chart_expiry', $user, $warehouseId, function () use ($user, $warehouseId) {
            $now    = now()->toDateString();
            $d30    = now()->addDays(30)->toDateString();
            $d90    = now()->addDays(90)->toDateString();

            $base = StockBalance::join('batches', 'stock_balances.batch_id', '=', 'batches.id')
                ->whereNotNull('batches.expiry_date')
                ->where('stock_balances.qty_on_hand', '>', 0);

            if (! $user->canAccessAllVirtualWarehouses()) {
                $vwIds = $user->accessibleVirtualWarehouseIds();
                if (! empty($vwIds)) {
                    $base->whereIn('stock_balances.virtual_warehouse_id', $vwIds);
                }
            }

            if ($warehouseId) {
                $base->whereHas('location', fn ($q) => $q->where('warehouse_id', $warehouseId));
            }

            $expired = (clone $base)->where('batches.expiry_date', '<', $now)->sum('stock_balances.qty_on_hand');
            $soon    = (clone $base)->whereBetween('batches.expiry_date', [$now, $d30])->sum('stock_balances.qty_on_hand');
            $medium  = (clone $base)->whereBetween('batches.expiry_date', [$d30, $d90])->sum('stock_balances.qty_on_hand');
            $far     = (clone $base)->where('batches.expiry_date', '>', $d90)->sum('stock_balances.qty_on_hand');

            return [
                'categories' => ['Expired', '0–30 days', '31–90 days', '90+ days'],
                'series'     => [['name' => 'Qty', 'data' => [
                    (float) $expired, (float) $soon, (float) $medium, (float) $far,
                ]]],
            ];
        });
    }

    /** Chart 7: top 10 items by dispatched qty in period. */
    public function chartTopMovingItems(User $user, int $days = 30): array
    {
        return $this->remember('chart_top_items', $user, $days, function () use ($user, $days) {
            $from = now()->subDays($days)->startOfDay();

            $rows = StockMovement::join('stock_transactions', 'stock_movements.stock_transaction_id', '=', 'stock_transactions.id')
                ->join('items', 'stock_movements.item_id', '=', 'items.id')
                ->where('stock_transactions.type', MovementType::Dispatch->value)
                ->where('stock_movements.qty', '<', 0)
                ->where('stock_transactions.posted_at', '>=', $from)
                ->groupBy('stock_movements.item_id', 'items.name', 'items.sku')
                ->select('items.name', 'items.sku', DB::raw('ABS(SUM(stock_movements.qty)) as total'))
                ->orderByDesc('total')
                ->limit(10)
                ->get();

            return [
                'categories' => $rows->map(fn ($r) => $r->sku ?: $r->name)->all(),
                'series'     => [['name' => 'Dispatched Qty', 'data' => $rows->map(fn ($r) => (float) $r->total)->all()]],
            ];
        });
    }

    /** Chart 8: dwell days per active container, coloured by warning/critical. */
    public function chartContainerDwell(User $user): array
    {
        return $this->remember('chart_container_dwell', $user, null, function () use ($user) {
            $activeValues = array_column(
                array_filter(ContainerStatus::cases(), fn ($s) => $s->isActive()),
                'value'
            );

            $containers = $this->containerQuery->queryFor($user)
                ->whereIn('status', $activeValues)
                ->whereNotNull('arrived_at')
                ->orderByDesc('arrived_at')
                ->limit(20)
                ->get(['id', 'container_no', 'arrived_at', 'free_days']);

            if ($containers->isEmpty()) {
                return ['categories' => [], 'series' => []];
            }

            $labels  = [];
            $okData  = [];
            $warnData = [];
            $critData = [];

            foreach ($containers as $c) {
                $dwell  = $c->dwellDays();
                $status = $c->dwellStatus();
                $labels[] = $c->container_no;
                $okData[]   = $status === 'ok'       ? $dwell : 0;
                $warnData[] = $status === 'warning'   ? $dwell : 0;
                $critData[] = $status === 'critical'  ? $dwell : 0;
            }

            return [
                'categories' => $labels,
                'series'     => [
                    ['name' => 'OK',       'data' => $okData],
                    ['name' => 'Warning',  'data' => $warnData],
                    ['name' => 'Critical', 'data' => $critData],
                ],
            ];
        });
    }

    // ── Cache invalidation ────────────────────────────────────────────────────

    public static function clearForUser(int $userId): void
    {
        $keys = [
            'stock_on_hand', 'open_orders', 'pending_approvals', 'to_receive',
            'awaiting_putaway', 'to_pick', 'in_transit', 'low_stock', 'expiring',
            'containers_in_yard', 'my_approvals', 'overdue_orders',
            'recent_variances', 'recent_activity',
            'chart_inbound_outbound', 'chart_stock_vw', 'chart_stock_category',
            'chart_order_pipeline', 'chart_bin_util', 'chart_expiry',
            'chart_top_items', 'chart_container_dwell',
        ];

        foreach ($keys as $key) {
            // Clear for common warehouse variations (0 = no filter, plus a few)
            foreach ([0, 1, 2, 3, 4, 5] as $wId) {
                Cache::forget("dashboard.{$key}.u{$userId}.w{$wId}");
            }
            Cache::forget("dashboard.{$key}.u{$userId}");
        }
    }

    public static function clearAll(): void
    {
        // Broad invalidation after mass operations — relies on cache key prefix
        // In production replace with cache tags if the driver supports them.
        Cache::flush();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function remember(string $key, User $user, ?int $warehouseId, callable $callback): mixed
    {
        $cacheKey = "dashboard.{$key}.u{$user->id}.w" . ($warehouseId ?? 0);
        return Cache::remember($cacheKey, self::TTL, $callback);
    }

    private function applyVwScope(\Illuminate\Database\Eloquent\Builder $query, User $user): void
    {
        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            if (! empty($vwIds)) {
                $query->whereIn('virtual_warehouse_id', $vwIds);
            }
        }
    }

    private function applyWarehouseScope(\Illuminate\Database\Eloquent\Builder $query, ?int $warehouseId): void
    {
        if ($warehouseId) {
            $query->whereHas('location', fn ($q) => $q->where('warehouse_id', $warehouseId));
        }
    }
}
