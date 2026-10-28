<?php

declare(strict_types=1);

namespace App\Reports\Stock;

use App\Contracts\Reports\ReportDefinition;
use App\Models\User;
use App\Services\Inventory\StockQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class StockOnHandReport implements ReportDefinition
{
    public function key(): string   { return 'stock-on-hand'; }
    public function label(): string { return 'Stock On Hand'; }
    public function group(): string { return 'Stock'; }
    public function periodType(): string { return 'as_of'; }
    public function pdfEnabled(): bool   { return true; }

    public function columns(): array
    {
        return [
            ['key' => 'item.sku',              'label' => 'SKU',         'align' => 'left',   'format' => 'text'],
            ['key' => 'item.name',             'label' => 'Item',        'align' => 'left',   'format' => 'text'],
            ['key' => 'item.category.name',    'label' => 'Category',    'align' => 'left',   'format' => 'text'],
            ['key' => 'virtualWarehouse.name', 'label' => 'Warehouse',   'align' => 'left',   'format' => 'text'],
            ['key' => 'location.code',         'label' => 'Location',    'align' => 'left',   'format' => 'text'],
            ['key' => 'item.baseUom.code',     'label' => 'UOM',         'align' => 'center', 'format' => 'text'],
            ['key' => 'stock_status',          'label' => 'Status',      'align' => 'center', 'format' => 'enum'],
            ['key' => 'qty_on_hand',           'label' => 'On Hand',     'align' => 'right',  'format' => 'number', 'total' => true],
            ['key' => 'qty_allocated',         'label' => 'Allocated',   'align' => 'right',  'format' => 'number', 'total' => true],
            ['key' => 'qty_available',         'label' => 'Available',   'align' => 'right',  'format' => 'number', 'total' => true],
        ];
    }

    public function groupBy(): string
    {
        return 'virtualWarehouse.name';
    }

    public function query(array $filters, User $user): Builder
    {
        return app(StockQueryService::class)
            ->stockOnHandBuilder(
                virtualWarehouseId: $filters['warehouse_id'] ?? null,
                departmentId:       $filters['department_id'] ?? null,
                asOfDate:           $filters['as_of_date'] ?? null,
                user:               $user,
            )
            ->selectRaw('stock_balances.*, (qty_on_hand - qty_allocated) as qty_available')
            ->with(['item.category', 'item.baseUom', 'location', 'virtualWarehouse'])
            ->orderBy('virtual_warehouse_id')
            ->orderBy('item_id');
    }

    public function summary(Collection $rows): array
    {
        $onHand    = $rows->sum('qty_on_hand');
        $allocated = $rows->sum('qty_allocated');

        return [
            'SKUs'      => number_format($rows->pluck('item_id')->unique()->count()),
            'On Hand'   => number_format((float) $onHand, 2),
            'Allocated' => number_format((float) $allocated, 2),
            'Available' => number_format((float) ($onHand - $allocated), 2),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return [
            'qty_on_hand'   => $rows->sum('qty_on_hand'),
            'qty_allocated' => $rows->sum('qty_allocated'),
            'qty_available' => $rows->sum('qty_available'),
        ];
    }
}
