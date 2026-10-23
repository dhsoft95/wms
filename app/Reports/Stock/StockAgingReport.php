<?php

declare(strict_types=1);

namespace App\Reports\Stock;

use App\Contracts\Reports\ReportDefinition;
use App\Models\User;
use App\Services\Inventory\StockQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class StockAgingReport implements ReportDefinition
{
    public function key(): string   { return 'stock-aging'; }
    public function label(): string { return 'Stock Aging'; }
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
            ['key' => 'qty_on_hand',           'label' => 'On Hand',    'align' => 'right',  'format' => 'number'],
            ['key' => 'age_days',              'label' => 'Age (days)', 'align' => 'right',  'format' => 'number'],
            ['key' => 'age_bucket',            'label' => 'Age Bucket', 'align' => 'center', 'format' => 'text'],
        ];
    }

    public function query(array $filters, User $user): Builder
    {
        $asOf = $filters['as_of_date'] ?? now()->format('Y-m-d');

        return app(StockQueryService::class)
            ->stockOnHandBuilder(
                virtualWarehouseId: $filters['warehouse_id'] ?? null,
                departmentId: $filters['department_id'] ?? null,
                asOfDate: $asOf,
                user: $user,
            )
            ->select('stock_balances.*')
            ->selectRaw(
                'DATEDIFF(?, received_at) as age_days,
                CASE
                    WHEN DATEDIFF(?, received_at) <= 30  THEN \'0-30 days\'
                    WHEN DATEDIFF(?, received_at) <= 90  THEN \'31-90 days\'
                    WHEN DATEDIFF(?, received_at) <= 180 THEN \'91-180 days\'
                    ELSE \'180+ days\'
                END as age_bucket',
                [$asOf, $asOf, $asOf, $asOf]
            )
            ->with(['item.category', 'location', 'virtualWarehouse'])
            ->orderByRaw('DATEDIFF(?, received_at) DESC', [$asOf]);
    }

    public function summary(Collection $rows): array
    {
        return [
            'Items'       => $rows->count(),
            'On Hand'     => number_format((float) $rows->sum('qty_on_hand'), 2),
            'Avg Age (d)' => (string) (int) round((float) ($rows->avg('age_days') ?? 0)),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return ['qty_on_hand' => $rows->sum('qty_on_hand')];
    }
}
