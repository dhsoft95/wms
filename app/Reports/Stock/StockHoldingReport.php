<?php

declare(strict_types=1);

namespace App\Reports\Stock;

use App\Contracts\Reports\ReportDefinition;
use App\Models\User;
use App\Services\Inventory\StockQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class StockHoldingReport implements ReportDefinition
{
    public function key(): string   { return 'stock-holding'; }
    public function label(): string { return 'Stock Holding'; }
    public function group(): string { return 'Stock'; }
    public function periodType(): string { return 'as_of'; }
    public function pdfEnabled(): bool   { return false; }

    public function columns(): array
    {
        return [
            ['key' => 'item.sku',              'label' => 'SKU',        'align' => 'left',   'format' => 'text'],
            ['key' => 'item.name',             'label' => 'Item',       'align' => 'left',   'format' => 'text'],
            ['key' => 'item.category.name',    'label' => 'Category',   'align' => 'left',   'format' => 'text'],
            ['key' => 'virtualWarehouse.name', 'label' => 'Warehouse',  'align' => 'left',   'format' => 'text'],
            ['key' => 'location.code',         'label' => 'Location',   'align' => 'left',   'format' => 'text'],
            ['key' => 'stock_status',          'label' => 'Status',     'align' => 'center', 'format' => 'enum'],
            ['key' => 'qty_on_hand',           'label' => 'On Hand',    'align' => 'right',  'format' => 'number'],
            ['key' => 'qty_allocated',         'label' => 'Allocated',  'align' => 'right',  'format' => 'number'],
            ['key' => 'received_at',           'label' => 'Received',   'align' => 'left',   'format' => 'date'],
        ];
    }

    public function query(array $filters, User $user): Builder
    {
        return app(StockQueryService::class)
            ->stockOnHandBuilder(
                virtualWarehouseId: $filters['warehouse_id'] ?? null,
                departmentId: $filters['department_id'] ?? null,
                asOfDate: $filters['as_of_date'] ?? null,
                user: $user,
            )
            ->with(['item.category', 'location', 'virtualWarehouse'])
            ->orderBy('item_id');
    }

    public function summary(Collection $rows): array
    {
        return [
            'SKUs'      => $rows->count(),
            'On Hand'   => number_format((float) $rows->sum('qty_on_hand'), 2),
            'Allocated' => number_format((float) $rows->sum('qty_allocated'), 2),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return [
            'qty_on_hand'   => $rows->sum('qty_on_hand'),
            'qty_allocated' => $rows->sum('qty_allocated'),
        ];
    }
}
