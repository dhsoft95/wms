<?php

declare(strict_types=1);

namespace App\Reports\Activity;

use App\Contracts\Reports\ReportDefinition;
use App\Enums\Inventory\MovementType;
use App\Models\Inventory\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class StockMovementReport implements ReportDefinition
{
    public function key(): string   { return 'stock-movement'; }
    public function label(): string { return 'Stock Movements'; }
    public function group(): string { return 'Activity'; }
    public function periodType(): string { return 'range'; }
    public function pdfEnabled(): bool   { return true; }

    public function columns(): array
    {
        return [
            ['key' => 'transaction.posted_at',   'label' => 'Date',      'align' => 'left',   'format' => 'datetime'],
            ['key' => 'transaction.number',       'label' => 'Tx No',     'align' => 'left',   'format' => 'text'],
            ['key' => 'transaction.type',         'label' => 'Type',      'align' => 'left',   'format' => 'enum'],
            ['key' => 'transaction.reference_no', 'label' => 'Reference', 'align' => 'left',   'format' => 'text'],
            ['key' => 'item.sku',                 'label' => 'SKU',       'align' => 'left',   'format' => 'text'],
            ['key' => 'item.name',                'label' => 'Item',      'align' => 'left',   'format' => 'text'],
            ['key' => 'virtualWarehouse.name',    'label' => 'Warehouse', 'align' => 'left',   'format' => 'text'],
            ['key' => 'location.code',            'label' => 'Location',  'align' => 'left',   'format' => 'text'],
            ['key' => 'qty',                      'label' => 'Qty',       'align' => 'right',  'format' => 'signed', 'tone' => 'signed', 'total' => true],
            ['key' => 'enteredUom.code',          'label' => 'UOM',       'align' => 'center', 'format' => 'text'],
        ];
    }

    public function query(array $filters, User $user): Builder
    {
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $filters['date_to']   ?? now()->format('Y-m-d');

        $excluded = [
            MovementType::Reversal->value,
            MovementType::SerialCapture->value,
        ];

        $query = StockMovement::withoutGlobalScopes()
            ->join('stock_transactions as st', 'st.id', '=', 'stock_movements.stock_transaction_id')
            ->whereNotIn('st.type', $excluded)
            ->where('st.posted_at', '>=', $dateFrom . ' 00:00:00')
            ->where('st.posted_at', '<=', $dateTo . ' 23:59:59')
            ->select('stock_movements.*')
            ->with(['transaction', 'item', 'location', 'virtualWarehouse', 'enteredUom'])
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
        $totalIn  = $rows->where('qty', '>', 0)->sum('qty');
        $totalOut = $rows->where('qty', '<', 0)->sum('qty');

        return [
            'Transactions' => number_format($rows->pluck('stock_transaction_id')->unique()->count()),
            'Lines'        => number_format($rows->count()),
            'Total In'     => number_format((float) $totalIn, 2),
            'Total Out'    => number_format(abs((float) $totalOut), 2),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return [
            'qty' => $rows->sum('qty'),
        ];
    }
}
