<?php

declare(strict_types=1);

namespace App\Reports\Orders;

use App\Contracts\Reports\ReportDefinition;
use App\Enums\Inbound\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PendingOrdersReport implements ReportDefinition
{
    private const TERMINAL = [
        PurchaseOrderStatus::VerifiedClosed->value,
        PurchaseOrderStatus::Cancelled->value,
        PurchaseOrderStatus::Rejected->value,
    ];

    public function key(): string   { return 'pending-orders'; }
    public function label(): string { return 'Pending Orders'; }
    public function group(): string { return 'Orders'; }
    public function periodType(): string { return 'range'; }
    public function pdfEnabled(): bool   { return true; }

    public function columns(): array
    {
        return [
            ['key' => 'number',          'label' => 'PO Number',       'align' => 'left',   'format' => 'text'],
            ['key' => 'supplier.name',   'label' => 'Supplier',        'align' => 'left',   'format' => 'text'],
            ['key' => 'department.name', 'label' => 'Department',      'align' => 'left',   'format' => 'text'],
            ['key' => 'source',          'label' => 'Type',            'align' => 'center', 'format' => 'enum'],
            ['key' => 'expected_date',   'label' => 'Expected Date',   'align' => 'left',   'format' => 'date'],
            ['key' => 'status',          'label' => 'Status',          'align' => 'center', 'format' => 'enum'],
            ['key' => 'age_days',        'label' => 'Age (days)',      'align' => 'right',  'format' => 'number'],
            ['key' => 'lines_count',     'label' => 'Lines',           'align' => 'right',  'format' => 'number'],
        ];
    }

    public function query(array $filters, User $user): Builder
    {
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $filters['date_to']   ?? now()->format('Y-m-d');

        $query = PurchaseOrder::query()
            ->whereNotIn('status', self::TERMINAL)
            ->whereBetween('submitted_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->select('purchase_orders.*')
            ->selectRaw('DATEDIFF(NOW(), submitted_at) as age_days')
            ->withCount('lines')
            ->with(['supplier', 'department']);

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
            'Orders'      => $rows->count(),
            'Avg Age (d)' => (string) (int) round((float) ($rows->avg('age_days') ?? 0)),
            'Total Lines' => (string) (int) $rows->sum('lines_count'),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return ['lines_count' => $rows->sum('lines_count')];
    }
}
