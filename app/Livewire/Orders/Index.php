<?php

declare(strict_types=1);

namespace App\Livewire\Orders;

use App\Models\DeliveryOrder;
use App\Models\InternalMovement;
use App\Models\PurchaseOrder;
use App\Models\StockTransfer;
use App\Models\WarehouseTransfer;
use App\Services\Outbound\DeliveryOrderWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $tab = 'all'; // all | po | mrn | transfer

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public bool    $showTypeModal    = false;
    public bool    $showTransferStep = false;

    // Delete modal state (system-admin only)
    public ?string $deleteType   = null;
    public ?int    $deleteId     = null;
    public string  $deleteNumber = '';

    // Bulk selection (system-admin only)
    /** @var array<array{type:string,id:int,number:string}> */
    public array $selectedItems      = [];
    public bool  $showBulkDeleteModal = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->selectedItems = [];
    }

    public function updatedTab(): void
    {
        $this->resetPage();
        $this->statusFilter  = '';
        $this->selectedItems = [];
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedItems = [];
    }

    public function updatingPage(): void
    {
        $this->selectedItems = [];
    }

    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        $user           = auth()->user();
        $isUnrestricted = $user->hasAnyRole(config('wms.vw_unrestricted_roles', [
            'system-admin', 'warehouse-manager', 'viewer-auditor', 'storekeeper', 'scanner-operator',
        ]));

        // Accessible VW IDs for restricted users
        $accessibleVwIds = $isUnrestricted ? [] : $user->accessibleVirtualWarehouseIds();

        // RFC orders are only visible to WM/admin or the specific department head
        $canSeeRfc    = $user->hasAnyRole(['warehouse-manager', 'system-admin']);
        $safeStatusFilter = (! $canSeeRfc && $this->statusFilter === 'returned_for_correction')
            ? '' : $this->statusFilter;

        // ── PO query ──────────────────────────────────────────────────────────
        $poQ = DB::table('purchase_orders')
            ->leftJoin('virtual_warehouses as povw', 'purchase_orders.virtual_warehouse_id', '=', 'povw.id')
            ->leftJoin('departments as podept', 'purchase_orders.department_id', '=', 'podept.id')
            ->select(
                DB::raw("'po' as rec_type"),
                'purchase_orders.id', 'purchase_orders.number',
                'purchase_orders.virtual_warehouse_id',
                'purchase_orders.warehouse_id',
                DB::raw('povw.name as department_name'),
                DB::raw('expected_date as doc_date'),
                'purchase_orders.status', 'purchase_orders.created_at',
                DB::raw('(
                    SELECT ROUND(
                        COALESCE(SUM(gl.received_good_qty), 0) / NULLIF(SUM(pl.qty), 0) * 100, 0
                    )
                    FROM purchase_order_lines pl
                    LEFT JOIN grn_lines gl ON gl.purchase_order_line_id = pl.id
                    WHERE pl.purchase_order_id = purchase_orders.id
                ) as progress_pct')
            )
            ->when($this->tab !== 'all' && $this->tab !== 'po', fn ($q) => $q->whereRaw('1=0'))
            ->when($this->search, fn ($q) => $q->where('purchase_orders.number', 'like', "%{$this->search}%"))
            ->when($safeStatusFilter, fn ($q) => $q->where('purchase_orders.status', $safeStatusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('purchase_orders.created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('purchase_orders.created_at', '<=', $this->dateTo))
            ->when(! $isUnrestricted, function ($q) use ($user) {
                // Department users see only their own department's POs
                if ($user->hasRole('department-user') && $user->department_id) {
                    $q->where('purchase_orders.department_id', $user->department_id);
                    return;
                }

                // Other restricted roles are scoped by item-category warehouse assignments
                $whIds = \App\Models\ItemCategoryUser::where('user_id', $user->id)
                    ->pluck('warehouse_id')->unique()->values()->all();
                if (! empty($whIds)) {
                    $q->whereIn('purchase_orders.warehouse_id', $whIds);
                } else {
                    $q->whereRaw('1=0');
                }
            })
            ->when(! $canSeeRfc, fn ($q) => $q->where(function ($sq) use ($user) {
                $sq->where('purchase_orders.status', '!=', 'returned_for_correction')
                   ->orWhere('podept.head_user_id', $user->id);
            }));

        // ── DO / MRN query ────────────────────────────────────────────────────
        $doQ = DB::table('delivery_orders')
            ->join('virtual_warehouses', 'delivery_orders.virtual_warehouse_id', '=', 'virtual_warehouses.id')
            ->leftJoin('departments', 'delivery_orders.department_id', '=', 'departments.id')
            ->whereNull('delivery_orders.deleted_at')
            ->select(
                DB::raw("'mrn' as rec_type"),
                'delivery_orders.id', 'delivery_orders.number',
                'delivery_orders.virtual_warehouse_id',
                'delivery_orders.warehouse_id',
                'departments.name as department_name',
                DB::raw('delivery_orders.required_date as doc_date'),
                'delivery_orders.status', 'delivery_orders.created_at',
                DB::raw('0 as progress_pct')
            )
            ->when($this->tab !== 'all' && $this->tab !== 'mrn', fn ($q) => $q->whereRaw('1=0'))
            ->when($this->search, fn ($q) => $q->where('delivery_orders.number', 'like', "%{$this->search}%"))
            ->when($safeStatusFilter, fn ($q) => $q->where('delivery_orders.status', $safeStatusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('delivery_orders.created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('delivery_orders.created_at', '<=', $this->dateTo))
            ->when(! $isUnrestricted && ! empty($accessibleVwIds), fn ($q) => $q->whereIn('delivery_orders.virtual_warehouse_id', $accessibleVwIds))
            ->when(! $isUnrestricted && empty($accessibleVwIds), fn ($q) => $q->whereRaw('1=0'))
            ->when(! $canSeeRfc, fn ($q) => $q->where(function ($sq) use ($user) {
                $sq->where('delivery_orders.status', '!=', 'returned_for_correction')
                   ->orWhere('departments.head_user_id', $user->id);
            }));

        // ── In-warehouse movement (MOV) query ────────────────────────────────
        $movQ = DB::table('internal_movements')
            ->leftJoin('virtual_warehouses as mvw', 'internal_movements.virtual_warehouse_id', '=', 'mvw.id')
            ->select(
                DB::raw("'mov' as rec_type"),
                'internal_movements.id', 'internal_movements.number',
                'internal_movements.virtual_warehouse_id',
                DB::raw('NULL as warehouse_id'),
                DB::raw('mvw.name as department_name'),
                DB::raw('internal_movements.created_at as doc_date'),
                'internal_movements.status', 'internal_movements.created_at',
                DB::raw('0 as progress_pct')
            )
            ->whereNull('internal_movements.deleted_at')
            ->when($this->tab !== 'all' && $this->tab !== 'transfer', fn ($q) => $q->whereRaw('1=0'))
            ->when($this->search, fn ($q) => $q->where('internal_movements.number', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('internal_movements.status', $this->statusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('internal_movements.created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('internal_movements.created_at', '<=', $this->dateTo))
            ->when(! $isUnrestricted && ! empty($accessibleVwIds), fn ($q) => $q->whereIn('internal_movements.virtual_warehouse_id', $accessibleVwIds))
            ->when(! $isUnrestricted && empty($accessibleVwIds), fn ($q) => $q->whereRaw('1=0'));

        // ── Departmental transfer (TRF) query ─────────────────────────────────
        $trQ = DB::table('stock_transfers')
            ->leftJoin('virtual_warehouses as fvw', 'stock_transfers.from_virtual_warehouse_id', '=', 'fvw.id')
            ->whereNull('stock_transfers.deleted_at')
            ->select(
                DB::raw("'trf' as rec_type"),
                'stock_transfers.id', 'stock_transfers.number',
                'stock_transfers.from_virtual_warehouse_id as virtual_warehouse_id',
                DB::raw('NULL as warehouse_id'),
                DB::raw('fvw.name as department_name'),
                DB::raw('stock_transfers.created_at as doc_date'),
                'stock_transfers.status', 'stock_transfers.created_at',
                DB::raw('0 as progress_pct')
            )
            ->when($this->tab !== 'all' && $this->tab !== 'transfer', fn ($q) => $q->whereRaw('1=0'))
            ->when($this->search, fn ($q) => $q->where('stock_transfers.number', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('stock_transfers.status', $this->statusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('stock_transfers.created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('stock_transfers.created_at', '<=', $this->dateTo))
            ->when(! $isUnrestricted && ! empty($accessibleVwIds), fn ($q) => $q->where(fn ($q2) =>
                $q2->whereIn('stock_transfers.from_virtual_warehouse_id', $accessibleVwIds)
                   ->orWhereIn('stock_transfers.to_virtual_warehouse_id', $accessibleVwIds)
            ))
            ->when(! $isUnrestricted && empty($accessibleVwIds), fn ($q) => $q->whereRaw('1=0'));

        // ── Warehouse transfer (WTR) query ────────────────────────────────────
        $wtrQ = DB::table('warehouse_transfers')
            ->leftJoin('virtual_warehouses as fvw2', 'warehouse_transfers.from_virtual_warehouse_id', '=', 'fvw2.id')
            ->select(
                DB::raw("'wtr' as rec_type"),
                'warehouse_transfers.id', 'warehouse_transfers.number',
                'warehouse_transfers.from_virtual_warehouse_id as virtual_warehouse_id',
                'warehouse_transfers.from_warehouse_id as warehouse_id',
                DB::raw('fvw2.name as department_name'),
                DB::raw('warehouse_transfers.created_at as doc_date'),
                'warehouse_transfers.status', 'warehouse_transfers.created_at',
                DB::raw('0 as progress_pct')
            )
            ->whereNull('warehouse_transfers.deleted_at')
            ->when($this->tab !== 'all' && $this->tab !== 'transfer', fn ($q) => $q->whereRaw('1=0'))
            ->when($this->search, fn ($q) => $q->where('warehouse_transfers.number', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('warehouse_transfers.status', $this->statusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('warehouse_transfers.created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('warehouse_transfers.created_at', '<=', $this->dateTo))
            ->when(! $isUnrestricted && ! empty($accessibleVwIds), fn ($q) => $q->where(fn ($q2) =>
                $q2->whereIn('warehouse_transfers.from_virtual_warehouse_id', $accessibleVwIds)
                   ->orWhereIn('warehouse_transfers.to_virtual_warehouse_id', $accessibleVwIds)
            ))
            ->when(! $isUnrestricted && empty($accessibleVwIds), fn ($q) => $q->whereRaw('1=0'));

        // ── Union and paginate ────────────────────────────────────────────────
        $union = $poQ->union($doQ)->union($movQ)->union($trQ)->union($wtrQ);

        $perPage = 20;
        $page    = $this->getPage();

        $total = DB::query()->fromSub($union, 'orders_union')->count();
        $items = DB::query()->fromSub($union, 'orders_union')
            ->orderBy('created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    #[Computed]
    public function canCreateAny(): bool
    {
        $user = auth()->user();

        return $user->can('po.create') || $user->can('orders.create') || $user->can('transfers.create');
    }

    // ─── Bulk selection ───────────────────────────────────────────────────────

    #[Computed]
    public function allOnPageSelected(): bool
    {
        if (empty($this->selectedItems) || $this->orders->isEmpty()) {
            return false;
        }
        foreach ($this->orders as $order) {
            $key = $order->rec_type . ':' . $order->id;
            $found = false;
            foreach ($this->selectedItems as $item) {
                if ($item['type'] === $order->rec_type && (int) $item['id'] === (int) $order->id) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }
        return true;
    }

    public function selectAll(): void
    {
        abort_unless(auth()->user()->hasRole('system-admin'), 403);

        $this->selectedItems = $this->orders->map(fn ($o) => [
            'type'   => $o->rec_type,
            'id'     => (int) $o->id,
            'number' => $o->number,
        ])->all();
    }

    public function deselectAll(): void
    {
        $this->selectedItems = [];
    }

    public function toggleItem(string $type, int $id, string $number): void
    {
        foreach ($this->selectedItems as $k => $item) {
            if ($item['type'] === $type && (int) $item['id'] === $id) {
                array_splice($this->selectedItems, $k, 1);
                return;
            }
        }
        $this->selectedItems[] = ['type' => $type, 'id' => $id, 'number' => $number];
    }

    public function isSelected(string $type, int $id): bool
    {
        foreach ($this->selectedItems as $item) {
            if ($item['type'] === $type && (int) $item['id'] === $id) {
                return true;
            }
        }
        return false;
    }

    public function bulkDelete(): void
    {
        abort_unless(auth()->user()->hasRole('system-admin'), 403);

        if (empty($this->selectedItems)) {
            $this->showBulkDeleteModal = false;
            return;
        }

        $count = count($this->selectedItems);
        $errors = [];

        foreach ($this->selectedItems as $item) {
            try {
                DB::transaction(function () use ($item) {
                    $this->deleteType   = $item['type'];
                    $this->deleteId     = (int) $item['id'];
                    $this->deleteNumber = $item['number'];

                    match ($item['type']) {
                        'po'  => $this->deletePo(),
                        'mrn' => $this->deleteMrn(),
                        'mov' => $this->deleteMov(),
                        'trf' => $this->deleteTrf(),
                        'wtr' => $this->deleteWtr(),
                        default => null,
                    };
                });
            } catch (\Throwable $e) {
                $errors[] = $item['number'] . ': ' . $e->getMessage();
            }
        }

        $this->selectedItems       = [];
        $this->showBulkDeleteModal = false;
        $this->deleteType          = null;
        $this->deleteId            = null;
        $this->deleteNumber        = '';

        if (empty($errors)) {
            $this->dispatch('toast', message: "{$count} " . ($count === 1 ? 'record' : 'records') . " permanently deleted.", type: 'success');
        } else {
            $deleted = $count - count($errors);
            $this->dispatch('toast', message: "{$deleted}/{$count} deleted. Errors: " . implode('; ', $errors), type: 'warning');
        }

        $this->resetPage();
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function openDeleteModal(string $type, int $id, string $number): void
    {
        abort_unless(auth()->user()->hasRole('system-admin'), 403);

        $this->deleteType   = $type;
        $this->deleteId     = $id;
        $this->deleteNumber = $number;
    }

    public function deleteRecord(): void
    {
        abort_unless(auth()->user()->hasRole('system-admin'), 403);

        try {
            DB::transaction(function () {
                match ($this->deleteType) {
                    'po'  => $this->deletePo(),
                    'mrn' => $this->deleteMrn(),
                    'mov' => $this->deleteMov(),
                    'trf' => $this->deleteTrf(),
                    'wtr' => $this->deleteWtr(),
                    default => throw new \InvalidArgumentException("Unknown record type: {$this->deleteType}"),
                };
            });

            $this->dispatch('toast', message: "{$this->deleteNumber} permanently deleted.", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }

        $this->deleteType   = null;
        $this->deleteId     = null;
        $this->deleteNumber = '';
        $this->resetPage();
    }

    private function deletePo(): void
    {
        $po = PurchaseOrder::findOrFail($this->deleteId);
        $po->approvals()->delete();
        $po->assignments()->delete();
        $po->issues()->delete();
        foreach ($po->grns as $grn) {
            $grn->lines()->delete();
            $grn->delete();
        }
        $po->lines()->delete();
        // purchase_orders has no deleted_at column — hard delete directly
        DB::table('purchase_orders')->where('id', $po->id)->delete();
    }

    private function deleteMrn(): void
    {
        $do = DeliveryOrder::findOrFail($this->deleteId);
        app(DeliveryOrderWorkflow::class)->adminDelete($do, auth()->user());
    }

    private function deleteMov(): void
    {
        $mov = InternalMovement::findOrFail($this->deleteId);
        $mov->lines()->delete();
        $mov->delete();
    }

    private function deleteTrf(): void
    {
        $trf = StockTransfer::findOrFail($this->deleteId);
        $trf->approvals()->delete();
        $trf->lines()->delete();
        $trf->delete();
    }

    private function deleteWtr(): void
    {
        $wtr = WarehouseTransfer::findOrFail($this->deleteId);
        $wtr->lines()->delete();
        $wtr->delete();
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.orders.index')
            ->layout('components.layouts.app', [
                'title'           => 'Orders',
                'breadcrumbGroup' => null,
            ]);
    }
}
