<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Contracts\AiClient;
use App\Enums\ContainerStatus;
use App\Enums\Inventory\MovementType;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Models\Container;
use App\Models\CycleCountLine;
use App\Models\DeliveryOrder;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockTransaction;
use App\Models\ReportSummary;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ReportSummaryService
{
    private const DAILY_LIMIT_KEY = 'ai_summary_daily_';

    public function __construct(private readonly AiClient $ai) {}

    // ── Public API ────────────────────────────────────────────────────────────

    public function getSummary(
        string  $reportType,
        array   $filters,
        User    $user,
        string  $language = 'en',
        bool    $force = false,
    ): string {
        if (! config('ai.enabled', false)) {
            return '';
        }

        $scopeHash  = $this->scopeHash($reportType, $filters, $user, $language);
        $periodFrom = $filters['date_from'] ?? null;
        $periodTo   = $filters['date_to'] ?? null;

        if (! $force) {
            $q = ReportSummary::where('report_type', $reportType)
                ->where('scope_hash', $scopeHash)
                ->where('language', $language);

            $periodFrom !== null
                ? $q->whereDate('period_start', $periodFrom)
                : $q->whereNull('period_start');

            $periodTo !== null
                ? $q->whereDate('period_end', $periodTo)
                : $q->whereNull('period_end');

            $cached = $q->latest()->first();

            if ($cached) {
                return $cached->summary_text;
            }
        }

        if (! $this->withinDailyLimit($user)) {
            return 'Summary unavailable (daily limit reached).';
        }

        $payload = $this->buildPayload($reportType, $filters, $user);

        try {
            $result = $this->ai->summarise($this->systemInstruction($language), json_encode($payload));
        } catch (\Throwable $e) {
            Log::warning('AI summary failed', ['report' => $reportType, 'error' => $e->getMessage()]);

            return '';
        }

        ReportSummary::create([
            'report_type'   => $reportType,
            'scope_hash'    => $scopeHash,
            'period_start'  => $periodFrom,
            'period_end'    => $periodTo,
            'language'      => $language,
            'summary_text'  => $result->text,
            'provider'      => config('ai.provider', 'gemini'),
            'model'         => config('ai.gemini.model'),
            'input_tokens'  => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'created_by'    => $user->id,
        ]);

        try {
            activity()
                ->causedBy($user)
                ->withProperties([
                    'report_type'   => $reportType,
                    'provider'      => config('ai.provider', 'gemini'),
                    'model'         => config('ai.gemini.model'),
                    'input_tokens'  => $result->inputTokens,
                    'output_tokens' => $result->outputTokens,
                ])
                ->log('ai_summary_generated');
        } catch (\Throwable) {
            // Activity log failure is non-critical
        }

        return $result->text;
    }

    public function buildPayload(string $reportType, array $filters, User $user): array
    {
        $from = isset($filters['date_from']) ? \Carbon\Carbon::parse($filters['date_from']) : now()->subDays(30);
        $to   = isset($filters['date_to'])   ? \Carbon\Carbon::parse($filters['date_to'])   : now();

        return match ($reportType) {
            'stock-summary'       => $this->payloadStockSummary($user, $filters),
            'inbound'             => $this->payloadInbound($user, $from, $to, $filters),
            'outbound'            => $this->payloadOutbound($user, $from, $to, $filters),
            'expiry-ageing'       => $this->payloadExpiryAgeing($user, $filters),
            'yard'                => $this->payloadYard($user),
            'adjustments-counts'  => $this->payloadAdjustmentsAndCounts($user, $from, $to, $filters),
            default               => [],
        };
    }

    // ── Payload builders ──────────────────────────────────────────────────────

    private function payloadStockSummary(User $user, array $filters): array
    {
        $warehouseId = $filters['warehouse_id'] ?? null;

        $base = StockBalance::where('qty_on_hand', '>', 0);
        if (! $user->canAccessAllVirtualWarehouses()) {
            $base->whereIn('virtual_warehouse_id', $user->accessibleVirtualWarehouseIds());
        }
        if ($warehouseId) {
            $base->whereHas('location', fn ($q) => $q->where('warehouse_id', $warehouseId));
        }

        $total       = (float) (clone $base)->sum('qty_on_hand');
        $skuCount    = (clone $base)->distinct('item_id')->count('item_id');
        $belowMin    = (clone $base)->whereHas('item', fn ($q) => $q->whereColumn('stock_balances.qty_on_hand', '<', DB::raw('items.min_stock')))->count();
        $outOfStock  = \App\Models\Item::where('is_active', true)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('stock_balances')
                ->whereColumn('stock_balances.item_id', 'items.id')
                ->where('stock_balances.qty_on_hand', '>', 0)
            )->count();

        $byVw = (clone $base)
            ->join('virtual_warehouses', 'stock_balances.virtual_warehouse_id', '=', 'virtual_warehouses.id')
            ->groupBy('virtual_warehouses.name')
            ->select('virtual_warehouses.name', DB::raw('SUM(stock_balances.qty_on_hand) as total'))
            ->orderByDesc('total')->limit(10)->get()
            ->map(fn ($r) => ['department' => $r->name, 'qty' => (float) $r->total]);

        $byCategory = (clone $base)
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('item_categories', 'items.category_id', '=', 'item_categories.id')
            ->groupBy('item_categories.name')
            ->select('item_categories.name', DB::raw('SUM(stock_balances.qty_on_hand) as total'))
            ->orderByDesc('total')->limit(10)->get()
            ->map(fn ($r) => ['category' => $r->name, 'qty' => (float) $r->total]);

        return [
            'report'        => 'Stock Summary',
            'total_qty'     => $total,
            'sku_count'     => $skuCount,
            'below_min'     => $belowMin,
            'out_of_stock'  => $outOfStock,
            'by_department' => $byVw->all(),
            'by_category'   => $byCategory->all(),
        ];
    }

    private function payloadInbound(User $user, \Carbon\Carbon $from, \Carbon\Carbon $to, array $filters): array
    {
        $warehouseId = $filters['warehouse_id'] ?? null;

        $grnQuery = Grn::whereBetween('created_at', [$from, $to]);
        if ($warehouseId) {
            $grnQuery->where('warehouse_id', $warehouseId);
        }

        $grns = $grnQuery->count();

        $lineBase = GrnLine::whereHas('grn', function ($q) use ($from, $to, $warehouseId) {
            $q->whereBetween('created_at', [$from, $to]);
            if ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            }
        });

        $shortLines   = (clone $lineBase)->where('variance_qty', '<', 0)->count();
        $excessLines  = (clone $lineBase)->where('variance_qty', '>', 0)->count();
        $damagedLines = (clone $lineBase)->where('damage_qty', '>', 0)->count();
        $pendingVerif = (clone $lineBase)->whereNull('variance_type')->where('variance_qty', '!=', 0)->count();

        $topDiscrepancy = (clone $lineBase)
            ->join('grns', 'grn_lines.grn_id', '=', 'grns.id')
            ->join('purchase_orders', 'grns.purchase_order_id', '=', 'purchase_orders.id')
            ->join('partners', 'purchase_orders.supplier_partner_id', '=', 'partners.id')
            ->whereRaw('ABS(grn_lines.variance_qty) > 0')
            ->groupBy('partners.name')
            ->select('partners.name', DB::raw('COUNT(*) as variance_lines'))
            ->orderByDesc('variance_lines')->limit(5)->get()
            ->map(fn ($r) => ['supplier' => $r->name, 'variance_lines' => $r->variance_lines]);

        return [
            'report'           => 'Inbound Summary',
            'period'           => $from->format('d M Y') . ' – ' . $to->format('d M Y'),
            'grns_total'       => $grns,
            'short_lines'      => $shortLines,
            'excess_lines'     => $excessLines,
            'damaged_lines'    => $damagedLines,
            'pending_variance' => $pendingVerif,
            'top_discrepancy_suppliers' => $topDiscrepancy->all(),
        ];
    }

    private function payloadOutbound(User $user, \Carbon\Carbon $from, \Carbon\Carbon $to, array $filters): array
    {
        $base = DeliveryOrder::whereBetween('created_at', [$from, $to]);
        if (! $user->canAccessAllVirtualWarehouses()) {
            $base->whereIn('virtual_warehouse_id', $user->accessibleVirtualWarehouseIds());
        }

        $dispatched  = (clone $base)->where('status', DeliveryOrderStatus::Dispatched->value)->count();
        $onTime      = (clone $base)->where('status', DeliveryOrderStatus::Dispatched->value)
            ->whereColumn('dispatched_at', '<=', 'required_date')->count();
        $late        = $dispatched - $onTime;
        $pending     = (clone $base)->whereIn('status', [
            DeliveryOrderStatus::Submitted->value,
            DeliveryOrderStatus::Approved->value,
        ])->count();

        $topItems = StockMovement::join('stock_transactions', 'stock_movements.stock_transaction_id', '=', 'stock_transactions.id')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->where('stock_transactions.type', MovementType::Dispatch->value)
            ->where('stock_movements.qty', '<', 0)
            ->whereBetween('stock_transactions.posted_at', [$from, $to])
            ->groupBy('items.sku', 'items.name')
            ->select('items.sku', 'items.name', DB::raw('ABS(SUM(stock_movements.qty)) as total'))
            ->orderByDesc('total')->limit(5)->get()
            ->map(fn ($r) => ['sku' => $r->sku, 'name' => $r->name, 'qty' => (float) $r->total]);

        return [
            'report'          => 'Outbound Summary',
            'period'          => $from->format('d M Y') . ' – ' . $to->format('d M Y'),
            'dispatched'      => $dispatched,
            'on_time'         => $onTime,
            'late'            => $late,
            'on_time_pct'     => $dispatched > 0 ? round($onTime / $dispatched * 100, 1) : 0,
            'pending_approval' => $pending,
            'top_moving_items' => $topItems->all(),
        ];
    }

    private function payloadExpiryAgeing(User $user, array $filters): array
    {
        $warehouseId = $filters['warehouse_id'] ?? null;
        $now  = now()->toDateString();
        $d30  = now()->addDays(30)->toDateString();
        $d90  = now()->addDays(90)->toDateString();
        $d90b = now()->subDays(90)->toDateString();

        $base = StockBalance::join('batches', 'stock_balances.batch_id', '=', 'batches.id')
            ->whereNotNull('batches.expiry_date')
            ->where('stock_balances.qty_on_hand', '>', 0);
        if (! $user->canAccessAllVirtualWarehouses()) {
            $base->whereIn('stock_balances.virtual_warehouse_id', $user->accessibleVirtualWarehouseIds());
        }
        if ($warehouseId) {
            $base->whereHas('location', fn ($q) => $q->where('warehouse_id', $warehouseId));
        }

        $expired = (float) (clone $base)->where('batches.expiry_date', '<', $now)->sum('stock_balances.qty_on_hand');
        $soon    = (float) (clone $base)->whereBetween('batches.expiry_date', [$now, $d30])->sum('stock_balances.qty_on_hand');
        $medium  = (float) (clone $base)->whereBetween('batches.expiry_date', [$d30, $d90])->sum('stock_balances.qty_on_hand');
        $far     = (float) (clone $base)->where('batches.expiry_date', '>', $d90)->sum('stock_balances.qty_on_hand');

        $noMovement = StockBalance::where('qty_on_hand', '>', 0)
            ->whereDoesntHave('item', fn ($q) => $q->whereHas('stockMovements', fn ($m) =>
                $m->where('created_at', '>=', $d90b)
            ))->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->select('items.sku', 'items.name', DB::raw('SUM(stock_balances.qty_on_hand) as total'))
            ->groupBy('items.sku', 'items.name')
            ->orderByDesc('total')->limit(5)->get()
            ->map(fn ($r) => ['sku' => $r->sku, 'name' => $r->name, 'qty' => (float) $r->total]);

        return [
            'report'         => 'Expiry & Ageing',
            'expired_qty'    => $expired,
            'expiring_0_30'  => $soon,
            'expiring_31_90' => $medium,
            'ok_qty'         => $far,
            'slow_moving_items' => $noMovement->all(),
        ];
    }

    private function payloadYard(User $user): array
    {
        $active = array_column(
            array_filter(ContainerStatus::cases(), fn ($s) => $s->isActive()),
            'value'
        );

        $containers = Container::whereIn('status', $active)->get(['id', 'container_no', 'arrived_at', 'free_days', 'status']);

        $ok       = 0;
        $warning  = 0;
        $critical = 0;
        $details  = [];

        foreach ($containers as $c) {
            $status = $c->dwellStatus();
            match ($status) {
                'ok'       => $ok++,
                'warning'  => $warning++,
                'critical' => $critical++,
                default    => null,
            };
            if ($status !== 'ok') {
                $details[] = [
                    'container_no' => $c->container_no,
                    'dwell_days'   => $c->dwellDays(),
                    'status'       => $status,
                ];
            }
        }

        return [
            'report'               => 'Yard Status',
            'total_in_yard'        => $containers->count(),
            'within_dwell'         => $ok,
            'over_warning'         => $warning,
            'over_critical'        => $critical,
            'flagged_containers'   => array_slice($details, 0, 10),
        ];
    }

    private function payloadAdjustmentsAndCounts(User $user, \Carbon\Carbon $from, \Carbon\Carbon $to, array $filters): array
    {
        $warehouseId = $filters['warehouse_id'] ?? null;

        $adjQuery = StockAdjustment::whereBetween('created_at', [$from, $to]);
        if ($warehouseId) {
            $adjQuery->where('warehouse_id', $warehouseId);
        }

        $byReason = (clone $adjQuery)
            ->join('reason_codes', 'stock_adjustments.reason_code_id', '=', 'reason_codes.id')
            ->groupBy('reason_codes.name')
            ->select('reason_codes.name', DB::raw('COUNT(*) as total'))
            ->orderByDesc('total')->get()
            ->map(fn ($r) => ['reason' => $r->name, 'count' => $r->total]);

        $ccVariances = CycleCountLine::whereNotNull('variance')
            ->whereRaw('ABS(variance) > 0')
            ->whereBetween('updated_at', [$from, $to])
            ->count();

        $topVarianceBins = CycleCountLine::whereNotNull('variance')
            ->whereRaw('ABS(variance) > 0')
            ->whereBetween('updated_at', [$from, $to])
            ->join('locations', 'cycle_count_lines.location_id', '=', 'locations.id')
            ->groupBy('locations.code')
            ->select('locations.code', DB::raw('SUM(ABS(variance)) as total_variance'))
            ->orderByDesc('total_variance')->limit(5)->get()
            ->map(fn ($r) => ['bin' => $r->code, 'total_variance' => (float) $r->total_variance]);

        return [
            'report'              => 'Adjustments & Counts',
            'period'              => $from->format('d M Y') . ' – ' . $to->format('d M Y'),
            'total_adjustments'   => (clone $adjQuery)->count(),
            'by_reason'           => $byReason->all(),
            'count_variance_lines' => $ccVariances,
            'top_variance_bins'   => $topVarianceBins->all(),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function scopeHash(string $reportType, array $filters, User $user, string $language): string
    {
        $vwIds = $user->canAccessAllVirtualWarehouses() ? ['all'] : $user->accessibleVirtualWarehouseIds();
        sort($vwIds);

        $data = [
            'type'     => $reportType,
            'filters'  => $filters,
            'vw_ids'   => $vwIds,
            'language' => $language,
        ];

        return hash('sha256', json_encode($data));
    }

    private function withinDailyLimit(User $user): bool
    {
        $limit = (int) \App\Services\Foundation\SettingsService::get('ai.daily_limit_per_user', 20);
        if ($limit <= 0) {
            return true;
        }

        $today = today()->format('Y-m-d');
        $used  = ReportSummary::where('created_by', $user->id)
            ->whereDate('created_at', $today)
            ->count();

        return $used < $limit;
    }

    private function systemInstruction(string $language): string
    {
        $lang = $language === 'sw' ? 'Swahili' : 'English';

        return <<<PROMPT
        You are a warehouse analyst. Write 3 to 6 short bullet points summarising the warehouse report data provided.
        Use plain language. Only use numbers that appear in the data. Highlight the biggest changes, risks and items needing action.
        Do not guess or invent data. Do not write greetings or sign-offs. Answer in {$lang}.
        Format each point starting with a dash (-). Keep each point under 20 words.
        PROMPT;
    }
}
