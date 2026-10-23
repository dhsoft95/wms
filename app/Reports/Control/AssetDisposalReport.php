<?php

declare(strict_types=1);

namespace App\Reports\Control;

use App\Contracts\Reports\ReportDefinition;
use App\Models\DisposalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class AssetDisposalReport implements ReportDefinition
{
    public function key(): string   { return 'asset-disposal'; }
    public function label(): string { return 'Asset Disposal'; }
    public function group(): string { return 'Control'; }
    public function periodType(): string { return 'range'; }
    public function pdfEnabled(): bool   { return true; }

    public function columns(): array
    {
        return [
            ['key' => 'number',            'label' => 'Disposal No.', 'align' => 'left',   'format' => 'text'],
            ['key' => 'department.name',   'label' => 'Department',   'align' => 'left',   'format' => 'text'],
            ['key' => 'method',            'label' => 'Method',       'align' => 'center', 'format' => 'enum'],
            ['key' => 'status',            'label' => 'Status',       'align' => 'center', 'format' => 'enum'],
            ['key' => 'lines_count',       'label' => 'Items',        'align' => 'right',  'format' => 'number'],
            ['key' => 'total_value',       'label' => 'Total Value',  'align' => 'right',  'format' => 'number'],
            ['key' => 'submitted_at',      'label' => 'Submitted',    'align' => 'left',   'format' => 'date'],
            ['key' => 'completed_at',      'label' => 'Completed',    'align' => 'left',   'format' => 'date'],
        ];
    }

    public function query(array $filters, User $user): Builder
    {
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $filters['date_to']   ?? now()->format('Y-m-d');

        $query = DisposalRequest::query()
            ->whereBetween('submitted_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->withCount('lines')
            ->with(['department']);

        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            $query->whereIn('virtual_warehouse_id', $vwIds);
        }

        if ($filters['warehouse_id'] ?? null) {
            $query->where('virtual_warehouse_id', $filters['warehouse_id']);
        }

        if ($filters['department_id'] ?? null) {
            $query->where('department_id', $filters['department_id']);
        }

        return $query->orderBy('submitted_at', 'desc');
    }

    public function summary(Collection $rows): array
    {
        return [
            'Disposals'   => $rows->count(),
            'Total Value' => number_format((float) $rows->sum('total_value'), 2),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return [
            'lines_count' => $rows->sum('lines_count'),
            'total_value' => $rows->sum('total_value'),
        ];
    }
}
