<?php

declare(strict_types=1);

namespace App\Reports\Control;

use App\Contracts\Reports\ReportDefinition;
use App\Enums\Inventory\MovementType;
use App\Models\Inventory\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class StockAdjustmentReport implements ReportDefinition
{
    public function key(): string   { return 'stock-adjustment'; }
    public function label(): string { return 'Stock Adjustments'; }
    public function group(): string { return 'Control'; }
    public function periodType(): string { return 'range'; }
    public function pdfEnabled(): bool   { return true; }

    public function columns(): array
    {
        return [
            ['key' => 'transaction.posted_at',   'label' => 'Date',       'align' => 'left',   'format' => 'datetime'],
            ['key' => 'transaction.number',       'label' => 'Tx No',      'align' => 'left',   'format' => 'text'],
            ['key' => 'adj_type',                 'label' => 'Type',       'align' => 'left',   'format' => 'text'],
            ['key' => 'item.sku',                 'label' => 'SKU',        'align' => 'left',   'format' => 'text'],
            ['key' => 'item.name',                'label' => 'Item',       'align' => 'left',   'format' => 'text'],
            ['key' => 'virtualWarehouse.name',    'label' => 'Warehouse',  'align' => 'left',   'format' => 'text'],
            ['key' => 'location.code',            'label' => 'Location',   'align' => 'left',   'format' => 'text'],
            ['key' => 'qty',                      'label' => 'Qty',        'align' => 'right',  'format' => 'signed', 'tone' => 'signed', 'total' => true],
            ['key' => 'enteredUom.code',          'label' => 'UOM',        'align' => 'center', 'format' => 'text'],
            ['key' => 'transaction.postedBy.name','label' => 'Adjusted By','align' => 'left',   'format' => 'text'],
            ['key' => 'transaction.notes',        'label' => 'Reason',     'align' => 'left',   'format' => 'text'],
        ];
    }

    public function query(array $filters, User $user): Builder
    {
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $filters['date_to']   ?? now()->format('Y-m-d');

        $adjTypes = [
            MovementType::Adjustment->value,
            MovementType::CountAdjustment->value,
        ];

        $query = StockMovement::withoutGlobalScopes()
            ->join('stock_transactions as st', 'st.id', '=', 'stock_movements.stock_transaction_id')
            ->whereIn('st.type', $adjTypes)
            ->where('st.posted_at', '>=', $dateFrom . ' 00:00:00')
            ->where('st.posted_at', '<=', $dateTo . ' 23:59:59')
            ->selectRaw("stock_movements.*, CASE st.type
                WHEN 'adjustment'       THEN 'Adjustment'
                WHEN 'count_adjustment' THEN 'Count Adjustment'
                ELSE st.type
            END as adj_type")
            ->with(['transaction.postedBy', 'item', 'location', 'virtualWarehouse', 'enteredUom'])
            ->orderBy('st.posted_at', 'desc')
            ->orderBy('st.id', 'desc');

        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            $query->whereIn('stock_movements.virtual_warehouse_id', $vwIds);
        }

        if ($filters['warehouse_id'] ?? null) {
            $query->where('stock_movements.virtual_warehouse_id', $filters['warehouse_id']);
        }

        if ($filters['department_id'] ?? null) {
            $query->whereHas('virtualWarehouse', fn ($q) => $q->where('department_id', $filters['department_id']));
        }

        return $query;
    }

    public function summary(Collection $rows): array
    {
        $positive = $rows->where('qty', '>', 0)->sum('qty');
        $negative = $rows->where('qty', '<', 0)->sum('qty');
        $manual   = $rows->where('adj_type', 'Adjustment')->count();
        $count    = $rows->where('adj_type', 'Count Adjustment')->count();

        return [
            'Total Adjustments' => number_format($rows->count()),
            'Manual'            => number_format($manual),
            'Count-Based'       => number_format($count),
            'Net Qty Change'    => number_format((float) ($positive + $negative), 2),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return [
            'qty' => $rows->sum('qty'),
        ];
    }
}
