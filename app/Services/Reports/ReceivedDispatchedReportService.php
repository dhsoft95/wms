<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\Inventory\MovementType;
use App\Models\User;
use App\Support\PdfBrand;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ReceivedDispatchedReportService
{
    public function build(array $filters, User $user): array
    {
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $filters['date_to']   ?? now()->format('Y-m-d');

        $received   = $this->queryReceived($dateFrom, $dateTo, $filters, $user);
        $dispatched = $this->queryDispatched($dateFrom, $dateTo, $filters, $user);
        $movements  = $this->queryMovementSummary($dateFrom, $dateTo, $filters, $user);
        $remarks    = $this->buildRemarks($received, $dispatched);

        return [
            'brand'       => PdfBrand::make(),
            'dateFrom'    => $dateFrom,
            'dateTo'      => $dateTo,
            'filters'     => $filters,
            'warehouseName' => $this->resolveWarehouseName($filters),
            'summary'     => $this->buildSummary($received, $dispatched),
            'received'    => $received,
            'dispatched'  => $dispatched,
            'movements'   => $movements,
            'remarks'     => $remarks,
            'generatedAt' => now()->format('d M Y H:i'),
            'generatedBy' => $user->name,
        ];
    }

    private function queryReceived(string $dateFrom, string $dateTo, array $filters, User $user): Collection
    {
        $query = DB::table('grn_lines')
            ->join('grns', 'grns.id', '=', 'grn_lines.grn_id')
            ->join('items', 'items.id', '=', 'grn_lines.item_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'grns.purchase_order_id')
            ->leftJoin('partners as sp', 'sp.id', '=', 'grns.supplier_partner_id')
            ->whereNull('grns.deleted_at')
            ->whereNotIn('grns.status', ['cancelled'])
            ->whereNotNull('grns.confirmed_at')
            ->whereBetween('grns.confirmed_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->select([
                'grns.confirmed_at as date',
                'grns.number as grn_number',
                DB::raw("COALESCE(NULLIF(TRIM(grns.delivery_note_ref), ''), NULLIF(TRIM(grns.vehicle_plate), ''), po.number) as reference"),
                'sp.name as supplier_name',
                'items.sku',
                'items.name as item_name',
                'grn_lines.expected_qty',
                'grn_lines.received_good_qty',
                'grn_lines.received_damaged_qty',
                'grn_lines.variance_qty',
                DB::raw("COALESCE(grn_lines.variance_type, '') as variance_type"),
                'grn_lines.remarks',
            ])
            ->orderBy('grns.confirmed_at')
            ->orderBy('grns.number');

        $this->applyVwFilters($query, 'grn_lines.virtual_warehouse_id', $filters, $user);

        return $query->get();
    }

    private function queryDispatched(string $dateFrom, string $dateTo, array $filters, User $user): Collection
    {
        $query = DB::table('delivery_order_lines as dol')
            ->join('delivery_orders as do', 'do.id', '=', 'dol.delivery_order_id')
            ->join('dispatches', 'dispatches.delivery_order_id', '=', 'do.id')
            ->join('items', 'items.id', '=', 'dol.item_id')
            ->leftJoin('partners as cp', 'cp.id', '=', 'do.consignee_partner_id')
            ->whereNull('do.deleted_at')
            ->whereBetween('dispatches.dispatched_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->select([
                DB::raw('MIN(dispatches.dispatched_at) as date'),
                DB::raw('MIN(dispatches.number) as dispatch_number'),
                'do.number as do_number',
                DB::raw("COALESCE(NULLIF(TRIM(do.mrn_number), ''), do.number) as order_ref"),
                'cp.name as consignee_name',
                'items.sku',
                'items.name as item_name',
                'dol.dispatched_qty',
                'dol.short_qty',
                DB::raw("TRIM(CONCAT(COALESCE(MIN(dispatches.vehicle_reg_no), ''), IF(MIN(dispatches.driver_name) IS NOT NULL AND MIN(dispatches.driver_name) != '', CONCAT(' / ', MIN(dispatches.driver_name)), ''))) as vehicle_driver"),
                'dol.id as line_id',
            ])
            ->groupBy(
                'dol.id',
                'do.id',
                'do.number',
                'do.mrn_number',
                'cp.name',
                'items.sku',
                'items.name',
                'dol.dispatched_qty',
                'dol.short_qty',
                'do.consignee_partner_id',
            )
            ->orderByRaw('MIN(dispatches.dispatched_at)')
            ->orderBy('do.id');

        $this->applyVwFilters($query, 'do.virtual_warehouse_id', $filters, $user);

        return $query->get();
    }

    private function queryMovementSummary(string $dateFrom, string $dateTo, array $filters, User $user): Collection
    {
        $excluded = [MovementType::Reversal->value, MovementType::SerialCapture->value];

        $movQuery = DB::table('stock_movements as sm')
            ->join('stock_transactions as st', 'st.id', '=', 'sm.stock_transaction_id')
            ->join('items', 'items.id', '=', 'sm.item_id')
            ->whereNotIn('st.type', $excluded)
            ->whereBetween('st.posted_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->select([
                'sm.item_id',
                'items.sku',
                'items.name as item_name',
                DB::raw("SUM(CASE WHEN st.type = 'receipt'                           THEN sm.qty      ELSE 0 END) as received_qty"),
                DB::raw("SUM(CASE WHEN st.type = 'dispatch'                          THEN ABS(sm.qty) ELSE 0 END) as dispatched_qty"),
                DB::raw("SUM(CASE WHEN st.type IN ('adjustment','count_adjustment')  THEN sm.qty      ELSE 0 END) as adjustment_qty"),
                DB::raw("SUM(CASE WHEN st.type = 'receipt'                           THEN sm.qty      ELSE 0 END)
                        - SUM(CASE WHEN st.type = 'dispatch'                         THEN ABS(sm.qty) ELSE 0 END)
                        + SUM(CASE WHEN st.type IN ('adjustment','count_adjustment') THEN sm.qty      ELSE 0 END)
                        as net_movement"),
            ])
            ->groupBy('sm.item_id', 'items.sku', 'items.name');

        $this->applyVwFilters($movQuery, 'sm.virtual_warehouse_id', $filters, $user);

        $movements = $movQuery->get();

        if ($movements->isEmpty()) {
            return $movements;
        }

        $itemIds = $movements->pluck('item_id')->unique();

        $balQuery = DB::table('stock_balances')
            ->whereIn('item_id', $itemIds)
            ->select('item_id', DB::raw('SUM(qty_on_hand) as closing_qty'))
            ->groupBy('item_id');

        $this->applyVwFilters($balQuery, 'virtual_warehouse_id', $filters, $user);

        $balances = $balQuery->get()->keyBy('item_id');

        return $movements->map(function ($row) use ($balances) {
            $closing       = (float) ($balances->get($row->item_id)?->closing_qty ?? 0);
            $net           = (float) $row->net_movement;
            $row->closing_qty = $closing;
            $row->opening_qty = round($closing - $net, 4);
            return $row;
        })->sortBy('sku')->values();
    }

    private function buildSummary(Collection $received, Collection $dispatched): array
    {
        $grnCount        = $received->pluck('grn_number')->unique()->count();
        $dispatchCount   = $dispatched->pluck('dispatch_number')->unique()->count();
        $totalReceived   = (float) $received->sum('received_good_qty') + (float) $received->sum('received_damaged_qty');
        $totalDispatched = (float) $dispatched->sum('dispatched_qty');
        $netMovement     = $totalReceived - $totalDispatched;

        $discrepancies = $received->filter(fn ($r) => $r->variance_qty != 0 || $r->received_damaged_qty > 0)->count()
            + $dispatched->filter(fn ($r) => $r->short_qty > 0)->count();

        return [
            'Total Receipts'   => number_format($grnCount),
            'Total Dispatches' => number_format($dispatchCount),
            'Qty Received'     => number_format($totalReceived, 2),
            'Qty Dispatched'   => number_format($totalDispatched, 2),
            'Net Movement'     => ($netMovement >= 0 ? '+' : '') . number_format($netMovement, 2),
            'Discrepancies'    => number_format($discrepancies),
        ];
    }

    private function buildRemarks(Collection $received, Collection $dispatched): Collection
    {
        $remarks = collect();

        foreach ($received as $row) {
            if ((float) $row->received_damaged_qty > 0) {
                $remarks->push([
                    'type' => 'Damage',
                    'ref'  => $row->grn_number,
                    'sku'  => $row->sku,
                    'item' => $row->item_name,
                    'note' => 'Received damaged: ' . number_format((float) $row->received_damaged_qty, 2) . ' units',
                ]);
            }
            if ((float) $row->variance_qty !== 0.0) {
                $sign = (float) $row->variance_qty > 0 ? '+' : '';
                $type = $row->variance_type ? " ({$row->variance_type})" : '';
                $remarks->push([
                    'type' => 'Variance',
                    'ref'  => $row->grn_number,
                    'sku'  => $row->sku,
                    'item' => $row->item_name,
                    'note' => "Variance: {$sign}" . number_format((float) $row->variance_qty, 2) . $type,
                ]);
            }
        }

        foreach ($dispatched as $row) {
            if ((float) $row->short_qty > 0) {
                $remarks->push([
                    'type' => 'Short',
                    'ref'  => $row->dispatch_number,
                    'sku'  => $row->sku,
                    'item' => $row->item_name,
                    'note' => 'Short shipped: ' . number_format((float) $row->short_qty, 2) . ' units',
                ]);
            }
        }

        return $remarks;
    }

    private function resolveWarehouseName(array $filters): ?string
    {
        if (! ($filters['warehouse_id'] ?? null)) {
            return null;
        }

        return DB::table('virtual_warehouses')
            ->where('id', $filters['warehouse_id'])
            ->value('name');
    }

    private function applyVwFilters(\Illuminate\Database\Query\Builder $query, string $vwColumn, array $filters, User $user): void
    {
        if (! $user->canAccessAllVirtualWarehouses()) {
            $query->whereIn($vwColumn, $user->accessibleVirtualWarehouseIds());
        }

        if ($filters['warehouse_id'] ?? null) {
            $query->where($vwColumn, $filters['warehouse_id']);
        }

        if ($filters['department_id'] ?? null) {
            $vwInDept = DB::table('virtual_warehouses')
                ->where('department_id', $filters['department_id'])
                ->pluck('id');
            $query->whereIn($vwColumn, $vwInDept);
        }
    }
}
