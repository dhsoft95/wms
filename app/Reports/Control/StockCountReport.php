<?php

declare(strict_types=1);

namespace App\Reports\Control;

use App\Contracts\Reports\ReportDefinition;
use App\Models\CycleCountLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class StockCountReport implements ReportDefinition
{
    public function key(): string   { return 'stock-count'; }
    public function label(): string { return 'Stock Count'; }
    public function group(): string { return 'Control'; }
    public function periodType(): string { return 'range'; }
    public function pdfEnabled(): bool   { return true; }

    public function columns(): array
    {
        return [
            ['key' => 'count.number',          'label' => 'Count No.',   'align' => 'left',  'format' => 'text'],
            ['key' => 'location.code',          'label' => 'Location',    'align' => 'left',  'format' => 'text'],
            ['key' => 'item.sku',               'label' => 'SKU',         'align' => 'left',  'format' => 'text'],
            ['key' => 'item.name',              'label' => 'Item',        'align' => 'left',  'format' => 'text'],
            ['key' => 'batch.batch_no',         'label' => 'Lot/Batch',   'align' => 'left',  'format' => 'text'],
            ['key' => 'uom.code',               'label' => 'UOM',         'align' => 'left',  'format' => 'text'],
            ['key' => 'system_qty',             'label' => 'System Qty',  'align' => 'right', 'format' => 'number', 'total' => true],
            ['key' => 'counted_qty',            'label' => 'Counted Qty', 'align' => 'right', 'format' => 'number', 'total' => true],
            ['key' => 'variance',               'label' => 'Variance',    'align' => 'right', 'format' => 'signed', 'total' => true],
            ['key' => 'accuracy_pct',           'label' => 'Accuracy %',  'align' => 'right', 'format' => 'percent'],
        ];
    }

    public function groupBy(): string
    {
        return 'count.virtualWarehouse.name';
    }

    public function rowClass($row): string
    {
        if ($row->counted_qty !== null && bccomp((string) ($row->variance ?? '0'), '0', 4) !== 0) {
            return 'warn';
        }
        return '';
    }

    public function query(array $filters, User $user): Builder
    {
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $filters['date_to']   ?? now()->format('Y-m-d');

        $query = CycleCountLine::query()
            ->join('cycle_counts', 'cycle_count_lines.cycle_count_id', '=', 'cycle_counts.id')
            ->whereNotNull('cycle_count_lines.counted_qty')
            ->whereBetween('cycle_count_lines.updated_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->select('cycle_count_lines.*')
            ->selectRaw(
                'CASE
                    WHEN cycle_count_lines.system_qty = 0 AND cycle_count_lines.counted_qty = 0 THEN 100.00
                    WHEN cycle_count_lines.system_qty = 0 THEN NULL
                    ELSE ROUND((1 - ABS(cycle_count_lines.variance) / cycle_count_lines.system_qty) * 100, 2)
                END as accuracy_pct'
            )
            ->with(['count.virtualWarehouse', 'location', 'item', 'batch', 'uom']);

        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            $query->whereIn('cycle_counts.virtual_warehouse_id', $vwIds);
        }

        if ($filters['warehouse_id'] ?? null) {
            $query->where('cycle_counts.virtual_warehouse_id', $filters['warehouse_id']);
        }

        if ($filters['department_id'] ?? null) {
            $query->whereHas('count', fn ($q) => $q->whereHas('virtualWarehouse', fn ($q2) => $q2->where('department_id', $filters['department_id'])));
        }

        return $query->orderBy('cycle_counts.virtual_warehouse_id')->orderBy('cycle_count_lines.updated_at', 'desc');
    }

    public function summary(Collection $rows): array
    {
        $counted  = $rows->whereNotNull('counted_qty');
        $total    = $counted->count();
        $accurate = $counted->filter(fn ($r) => $r->accuracy_pct !== null && (float) $r->accuracy_pct >= 95.0)->count();

        return [
            'Lines Counted'  => $total,
            'Accuracy ≥ 95%' => $total > 0 ? number_format($accurate / $total * 100, 1) . '%' : '—',
            'Total Variance' => number_format((float) $rows->sum('variance'), 2),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return [
            'system_qty'  => $rows->sum('system_qty'),
            'counted_qty' => $rows->sum('counted_qty'),
            'variance'    => $rows->sum('variance'),
        ];
    }
}
