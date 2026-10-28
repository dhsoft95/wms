<?php

declare(strict_types=1);

namespace App\Reports\Control;

use App\Contracts\Reports\ReportDefinition;
use App\Enums\Inventory\MovementType;
use App\Models\Inventory\StockBalance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class StockReconciliationReport implements ReportDefinition
{
    public function key(): string   { return 'stock-reconciliation'; }
    public function label(): string { return 'Stock Reconciliation'; }
    public function group(): string { return 'Control'; }
    public function periodType(): string { return 'range'; }
    public function pdfEnabled(): bool   { return true; }

    public function columns(): array
    {
        return [
            ['key' => 'item.sku',              'label' => 'SKU',          'align' => 'left',   'format' => 'text'],
            ['key' => 'item.name',             'label' => 'Item',         'align' => 'left',   'format' => 'text'],
            ['key' => 'virtualWarehouse.name', 'label' => 'Warehouse',    'align' => 'left',   'format' => 'text'],
            ['key' => 'opening_qty',           'label' => 'Opening',      'align' => 'right',  'format' => 'number'],
            ['key' => 'receipts_qty',          'label' => 'Received (+)', 'align' => 'right',  'format' => 'number'],
            ['key' => 'dispatches_qty',        'label' => 'Dispatched (−)','align' => 'right', 'format' => 'number'],
            ['key' => 'adjustments_qty',       'label' => 'Adjustments',  'align' => 'right',  'format' => 'signed', 'tone' => 'signed'],
            ['key' => 'net_movement',          'label' => 'Net Movement', 'align' => 'right',  'format' => 'signed', 'tone' => 'signed'],
            ['key' => 'qty_on_hand',           'label' => 'Closing',      'align' => 'right',  'format' => 'number', 'total' => true],
        ];
    }

    public function groupBy(): string
    {
        return 'virtualWarehouse.name';
    }

    public function query(array $filters, User $user): Builder
    {
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $filters['date_to']   ?? now()->format('Y-m-d');

        $excluded = [MovementType::Reversal->value, MovementType::SerialCapture->value];

        $movSub = DB::table('stock_movements as sm')
            ->join('stock_transactions as st', 'st.id', '=', 'sm.stock_transaction_id')
            ->whereNotIn('st.type', $excluded)
            ->where('st.posted_at', '>=', $dateFrom . ' 00:00:00')
            ->where('st.posted_at', '<=', $dateTo . ' 23:59:59')
            ->groupBy('sm.item_id', 'sm.virtual_warehouse_id')
            ->select([
                'sm.item_id',
                'sm.virtual_warehouse_id',
                DB::raw("SUM(CASE WHEN st.type = 'receipt' AND sm.qty > 0 THEN sm.qty ELSE 0 END) as receipts_qty"),
                DB::raw("SUM(CASE WHEN st.type IN ('dispatch','pick') AND sm.qty < 0 THEN ABS(sm.qty) ELSE 0 END) as dispatches_qty"),
                DB::raw("SUM(CASE WHEN st.type IN ('adjustment','count_adjustment') THEN sm.qty ELSE 0 END) as adjustments_qty"),
                DB::raw('SUM(sm.qty) as net_movement'),
            ]);

        $query = StockBalance::withoutGlobalScopes()
            ->leftJoinSub($movSub, 'mv', function ($join) {
                $join->on('mv.item_id', '=', 'stock_balances.item_id')
                     ->on('mv.virtual_warehouse_id', '=', 'stock_balances.virtual_warehouse_id');
            })
            ->selectRaw(implode(', ', [
                'stock_balances.*',
                'COALESCE(mv.receipts_qty,   0) as receipts_qty',
                'COALESCE(mv.dispatches_qty, 0) as dispatches_qty',
                'COALESCE(mv.adjustments_qty,0) as adjustments_qty',
                'COALESCE(mv.net_movement,   0) as net_movement',
                'stock_balances.qty_on_hand - COALESCE(mv.net_movement, 0) as opening_qty',
            ]))
            ->with(['item', 'virtualWarehouse'])
            ->orderBy('stock_balances.virtual_warehouse_id')
            ->orderBy('stock_balances.item_id');

        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            $query->whereIn('stock_balances.virtual_warehouse_id', $vwIds);
        }

        if ($filters['warehouse_id'] ?? null) {
            $query->where('stock_balances.virtual_warehouse_id', $filters['warehouse_id']);
        }

        if ($filters['department_id'] ?? null) {
            $query->whereHas('virtualWarehouse', fn ($q) => $q->where('department_id', $filters['department_id']));
        }

        return $query;
    }

    public function summary(Collection $rows): array
    {
        return [
            'Items'         => number_format($rows->count()),
            'Opening Stock' => number_format((float) $rows->sum('opening_qty'), 2),
            'Received'      => number_format((float) $rows->sum('receipts_qty'), 2),
            'Dispatched'    => number_format((float) $rows->sum('dispatches_qty'), 2),
            'Closing Stock' => number_format((float) $rows->sum('qty_on_hand'), 2),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return [
            'qty_on_hand' => $rows->sum('qty_on_hand'),
        ];
    }
}
