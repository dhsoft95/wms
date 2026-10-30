<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\GrnStatus;
use App\Enums\PutawayTaskStatus;
use App\Enums\ReceiptCondition;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\DeliveryOrderLine;
use App\Models\PutawayTask;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Services\Inventory\StockQueryService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class GeneralReportService
{
    // ─── Sheet 1 – Receipts ───────────────────────────────────────────────────

    public function receipts(array $filters, User $user): Collection
    {
        $from = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $to   = $filters['date_to']   ?? now()->format('Y-m-d');

        $lines = GrnLine::query()
            ->join('grns', 'grns.id', '=', 'grn_lines.grn_id')
            ->whereNotIn('grns.status', [GrnStatus::Cancelled->value])
            ->whereNotNull('grns.confirmed_at')
            ->whereBetween('grns.confirmed_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->select('grn_lines.*')
            ->with([
                'grn' => fn ($q) => $q->with(['supplierPartner', 'purchaseOrder', 'receivingDock']),
                'item',
                'uom',
                'virtualWarehouse.department',
                'purchaseOrderLine',
                'receipts',
            ])
            ->when(! $user->canAccessAllVirtualWarehouses(), function ($q) use ($user) {
                $q->whereIn('grn_lines.virtual_warehouse_id', $user->accessibleVirtualWarehouseIds());
            })
            ->when($filters['department_id'] ?? null, function ($q, $deptId) {
                $vwIds = VirtualWarehouse::where('department_id', $deptId)->pluck('id');
                $q->whereIn('grn_lines.virtual_warehouse_id', $vwIds);
            })
            ->orderBy('grns.confirmed_at')
            ->orderBy('grns.id')
            ->orderBy('grn_lines.id')
            ->get();

        if ($lines->isEmpty()) {
            return collect();
        }

        // Batch-load completed putaway locations: key = "grn_id-item_id"
        $grnIds    = $lines->pluck('grn_id')->unique()->values()->all();
        $putawayMap = PutawayTask::with('actualLocation')
            ->whereIn('grn_id', $grnIds)
            ->where('status', PutawayTaskStatus::Completed->value)
            ->whereNotNull('actual_location_id')
            ->get()
            ->keyBy(fn ($t) => "{$t->grn_id}-{$t->item_id}");

        return $lines->map(function (GrnLine $line) use ($putawayMap) {
            $putaway   = $putawayMap["{$line->grn_id}-{$line->item_id}"] ?? null;
            $location  = $putaway?->actualLocation?->code ?? $line->grn->receivingDock?->code ?? '';
            $isDamaged = $line->receipts->contains(fn ($r) => $r->condition === ReceiptCondition::Damaged);
            $serials   = $line->receipts->flatMap(fn ($r) => $r->serial_nos ?? [])->unique()->filter()->implode(', ');
            $unitPrice = (float) ($line->purchaseOrderLine?->unit_price
                ?? $line->receipts->where('unit_price', '>', 0)->first()?->unit_price
                ?? 0);
            $qty = (float) $line->received_good_qty;

            return (object) [
                'reception_date'      => $line->grn->confirmed_at,
                'grn_number'          => $line->grn->number ?? '',
                'reception_reference' => $line->grn->delivery_note_ref ?? '',
                'supplier'            => $line->grn->supplierPartner?->name ?? '',
                'department'          => $line->virtualWarehouse?->department?->name ?? '',
                'po_number'           => $line->grn->purchaseOrder?->number ?? '',
                'part_number'         => $line->item->part_number ?? '',
                'item_description'    => $line->item->name ?? '',
                'conformity'          => $isDamaged ? 'Damaged' : 'Good',
                'uom'                 => $line->uom->code ?? '',
                'quantity_received'   => $qty,
                'location'            => $location,
                'mic_number'          => $line->mic_number ?? '',
                'serial_number'       => $serials,
                'single_value'        => $unitPrice,
                'total_value'         => round($qty * $unitPrice, 4),
            ];
        });
    }

    // ─── Sheet 2 – Despatches ─────────────────────────────────────────────────

    public function despatches(array $filters, User $user): Collection
    {
        $from = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $to   = $filters['date_to']   ?? now()->format('Y-m-d');

        $lines = DeliveryOrderLine::query()
            ->join('delivery_orders as do_tbl', 'do_tbl.id', '=', 'delivery_order_lines.delivery_order_id')
            ->join('dispatches', 'dispatches.delivery_order_id', '=', 'do_tbl.id')
            ->whereNull('do_tbl.deleted_at')
            ->whereBetween('dispatches.dispatched_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('delivery_order_lines.dispatched_qty', '>', 0)
            ->selectRaw('delivery_order_lines.*, MIN(dispatches.dispatched_at) as shipping_date_raw')
            ->groupBy('delivery_order_lines.id')
            ->with([
                'deliveryOrder' => fn ($q) => $q->with([
                    'department',
                    'requestedBy',
                    'approvals' => fn ($a) => $a
                        ->where('decision', 'approved')
                        ->orderBy('level', 'desc')
                        ->with('decidedBy'),
                ]),
                'item',
                'uom',
                'allocations.stockBalance',
            ])
            ->when(! $user->canAccessAllVirtualWarehouses(), function ($q) use ($user) {
                $q->whereIn('do_tbl.virtual_warehouse_id', $user->accessibleVirtualWarehouseIds());
            })
            ->when($filters['department_id'] ?? null, function ($q, $deptId) {
                $q->where('do_tbl.department_id', $deptId);
            })
            ->orderByRaw('MIN(dispatches.dispatched_at)')
            ->orderBy('do_tbl.id')
            ->get();

        if ($lines->isEmpty()) {
            return collect();
        }

        // Build GRN lookup from all referenced stock_balances
        $allSBs = $lines
            ->flatMap(fn ($l) => $l->allocations->map(fn ($a) => $a->stockBalance))
            ->filter()
            ->unique('id');

        $grnLookup = $this->buildGrnLookup($allSBs);

        return $lines->flatMap(function ($line) use ($grnLookup) {
            $do       = $line->deliveryOrder;
            $approver = $do->approvals->first()?->decidedBy?->name ?? '';

            if ($line->allocations->isEmpty()) {
                return collect([$this->buildDespatchRow($line, $do, $approver, null, null)]);
            }

            return $line->allocations->map(function ($alloc) use ($line, $do, $approver, $grnLookup) {
                $sb      = $alloc->stockBalance;
                $grnData = null;
                if ($sb) {
                    $key     = $this->sbKey($sb->item_id, $sb->batch_id, $sb->lpn_id, $sb->virtual_warehouse_id);
                    $grnData = $grnLookup[$key] ?? null;
                }
                return $this->buildDespatchRow($line, $do, $approver, $alloc, $grnData);
            });
        });
    }

    private function buildDespatchRow(
        DeliveryOrderLine $line,
        $do,
        string $approver,
        $alloc,
        ?array $grnData,
    ): object {
        $grn       = $grnData['grn']      ?? null;
        $grnLine   = $grnData['grnLine']  ?? null;
        $receipts  = $grnData['receipts'] ?? collect();

        $isDamaged = $receipts->contains(fn ($r) => $r->condition === ReceiptCondition::Damaged);
        $serials   = $receipts->flatMap(fn ($r) => $r->serial_nos ?? [])->unique()->filter()->implode(', ');
        $unitPrice = (float) ($grnLine?->purchaseOrderLine?->unit_price ?? 0);
        $qty       = $alloc ? (float) $alloc->qty : (float) $line->dispatched_qty;

        return (object) [
            'shipping_date'    => $line->shipping_date_raw,
            'grn_number'       => $grn?->number ?? '',
            'order_number'     => $do->number ?? '',
            'req_department'   => $do->department?->name ?? '',
            'site_name'        => $do->delivery_address ?? '',
            'sub_contractor'   => '',
            'requestor'        => $do->requestedBy?->name ?? '',
            'approver'         => $approver,
            'stock_department' => $grnLine?->virtualWarehouse?->department?->name ?? '',
            'po_number'        => $grn?->purchaseOrder?->number ?? '',
            'part_number'      => $line->item->part_number ?? '',
            'item_description' => $line->item->name ?? '',
            'conformity'       => $isDamaged ? 'Damaged' : 'Good',
            'uom'              => $line->uom->code ?? '',
            'qty_ordered'      => (float) $line->requested_qty,
            'qty_delivered'    => $qty,
            'mic_number'       => $grnLine?->mic_number ?? '',
            'serial_number'    => $serials,
            'single_value'     => $unitPrice,
            'stock_value'      => round($qty * $unitPrice, 4),
        ];
    }

    // ─── Sheet 3 – Stock Ageing ───────────────────────────────────────────────

    public function stockAgeing(array $filters, User $user): Collection
    {
        $to = $filters['date_to'] ?? now()->format('Y-m-d');

        $balances = app(StockQueryService::class)
            ->stockOnHandBuilder(
                virtualWarehouseId: $filters['warehouse_id'] ?? null,
                departmentId:       $filters['department_id'] ?? null,
                asOfDate:           $to,
                user:               $user,
            )
            ->with(['item', 'virtualWarehouse.department'])
            ->whereNotNull('received_at')
            ->get();

        if ($balances->isEmpty()) {
            return collect();
        }

        $grnLookup = $this->buildGrnLookup($balances);
        $toDate    = Carbon::parse($to);

        return $balances->map(function ($sb) use ($grnLookup, $toDate) {
            $key       = $this->sbKey($sb->item_id, $sb->batch_id, $sb->lpn_id, $sb->virtual_warehouse_id);
            $grnData   = $grnLookup[$key] ?? null;
            $grn       = $grnData['grn']      ?? null;
            $grnLine   = $grnData['grnLine']  ?? null;
            $receipts  = $grnData['receipts'] ?? collect();

            $isDamaged    = $receipts->contains(fn ($r) => $r->condition === ReceiptCondition::Damaged);
            $unitPrice    = (float) ($grnLine?->purchaseOrderLine?->unit_price ?? 0);
            $qty          = (float) $sb->qty_on_hand;
            $dateIn       = $sb->received_at;
            $stayInDays   = $dateIn ? (int) Carbon::parse($dateIn)->diffInDays($toDate) : 0;
            $supplierSite = $grn?->supplierPartner?->name ?? $grn?->customerPartner?->name ?? '';

            return (object) [
                'supplier_site'     => $supplierSite,
                'inbound_reference' => $grn?->delivery_note_ref ?? '',
                'date_in'           => $dateIn,
                'grn_number'        => $grn?->number ?? '',
                'department'        => $sb->virtualWarehouse?->department?->name ?? '',
                'po_number'         => $grn?->purchaseOrder?->number ?? '',
                'part_number'       => $sb->item->part_number ?? '',
                'item_description'  => $sb->item->name ?? '',
                'conformity'        => $isDamaged ? 'Damaged' : 'Good',
                'quantity'          => $qty,
                'mic_number'        => $grnLine?->mic_number ?? '',
                'single_value'      => $unitPrice,
                'stock_value'       => round($qty * $unitPrice, 4),
                'stay_in_days'      => $stayInDays,
            ];
        })
        ->sortByDesc('stay_in_days')
        ->values();
    }

    // ─── GRN lookup ───────────────────────────────────────────────────────────

    /**
     * For a set of StockBalance records, find the source GRN for each unique
     * (item_id, batch_id, lpn_id, virtual_warehouse_id) combination by tracing
     * through the earliest Receipt stock_movement → stock_transaction.
     *
     * Returns a map keyed by sbKey() → ['grn', 'grnLine', 'receipts'].
     */
    private function buildGrnLookup(Collection $stockBalances): array
    {
        if ($stockBalances->isEmpty()) {
            return [];
        }

        $itemIds = $stockBalances->pluck('item_id')->unique()->values()->all();
        $vwIds   = $stockBalances->pluck('virtual_warehouse_id')->unique()->values()->all();

        // Earliest Receipt movement per (item, batch, lpn, vw) → source GRN id
        $traceRows = DB::table('stock_movements as sm')
            ->join('stock_transactions as st', 'st.id', '=', 'sm.stock_transaction_id')
            ->where('st.reference_type', 'grn')
            ->where('sm.qty', '>', 0)
            ->whereIn('sm.item_id', $itemIds)
            ->whereIn('sm.virtual_warehouse_id', $vwIds)
            ->orderBy('sm.id')
            ->select(['sm.item_id', 'sm.batch_id', 'sm.lpn_id', 'sm.virtual_warehouse_id', 'st.reference_id as grn_id'])
            ->get()
            ->groupBy(fn ($r) => $this->sbKey($r->item_id, $r->batch_id, $r->lpn_id, $r->virtual_warehouse_id))
            ->map(fn ($g) => $g->first()); // keep first (earliest) movement per key

        if ($traceRows->isEmpty()) {
            return [];
        }

        $grnIds = $traceRows->pluck('grn_id')->unique()->values()->all();

        $grns = Grn::with(['supplierPartner', 'customerPartner', 'purchaseOrder'])
            ->whereIn('id', $grnIds)
            ->get()
            ->keyBy('id');

        $grnLines = GrnLine::with(['virtualWarehouse.department', 'purchaseOrderLine', 'receipts'])
            ->whereIn('grn_id', $grnIds)
            ->whereIn('item_id', $itemIds)
            ->get()
            ->groupBy(fn ($l) => "{$l->grn_id}-{$l->item_id}")
            ->map(fn ($g) => $g->first());

        $lookup = [];
        foreach ($traceRows as $key => $row) {
            $grn     = $grns[$row->grn_id] ?? null;
            $grnLine = $grnLines["{$row->grn_id}-{$row->item_id}"] ?? null;

            $lookup[$key] = [
                'grn'      => $grn,
                'grnLine'  => $grnLine,
                'receipts' => $grnLine?->receipts ?? collect(),
            ];
        }

        return $lookup;
    }

    private function sbKey(int $itemId, ?int $batchId, ?int $lpnId, int $vwId): string
    {
        return "{$itemId}-" . ($batchId ?? 'NULL') . '-' . ($lpnId ?? 'NULL') . "-{$vwId}";
    }
}
