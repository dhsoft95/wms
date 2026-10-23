<?php

declare(strict_types=1);

namespace App\Reports\Orders;

use App\Contracts\Reports\ReportDefinition;
use App\Enums\Inbound\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PlannedOrdersReport implements ReportDefinition
{
    private const PLANNED_STATUSES = [
        PurchaseOrderStatus::ApprovedPendingAcceptance->value,
        PurchaseOrderStatus::AcceptedPendingAssignment->value,
        PurchaseOrderStatus::AssignedPendingReceiving->value,
    ];

    public function key(): string   { return 'planned-orders'; }
    public function label(): string { return 'Planned Orders'; }
    public function group(): string { return 'Orders'; }
    public function periodType(): string { return 'range'; }
    public function pdfEnabled(): bool   { return false; }

    public function columns(): array
    {
        return [
            ['key' => 'number',          'label' => 'PO No.',         'align' => 'left',   'format' => 'text'],
            ['key' => 'supplierPartner.name', 'label' => 'Supplier',   'align' => 'left',   'format' => 'text'],
            ['key' => 'department.name', 'label' => 'Department',     'align' => 'left',   'format' => 'text'],
            ['key' => 'source',          'label' => 'Type',           'align' => 'center', 'format' => 'enum'],
            ['key' => 'expected_date',   'label' => 'Expected Date',  'align' => 'left',   'format' => 'date'],
            ['key' => 'status',          'label' => 'Status',         'align' => 'center', 'format' => 'enum'],
            ['key' => 'lines_count',     'label' => 'Lines',          'align' => 'right',  'format' => 'number'],
            ['key' => 'days_until_due',  'label' => 'Days Until Due', 'align' => 'right',  'format' => 'number'],
        ];
    }

    public function query(array $filters, User $user): Builder
    {
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $filters['date_to']   ?? now()->addMonths(3)->format('Y-m-d');

        $query = PurchaseOrder::query()
            ->whereIn('status', self::PLANNED_STATUSES)
            ->whereNull('arrived_at')
            ->whereBetween('expected_date', [$dateFrom, $dateTo])
            ->select('purchase_orders.*')
            ->selectRaw('DATEDIFF(expected_date, NOW()) as days_until_due')
            ->withCount('lines')
            ->with(['supplierPartner', 'department']);

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

        return $query->orderBy('expected_date');
    }

    public function summary(Collection $rows): array
    {
        return [
            'Orders'       => $rows->count(),
            'Total Lines'  => (string) (int) $rows->sum('lines_count'),
            'Avg Days Due' => (string) (int) round((float) ($rows->avg('days_until_due') ?? 0)),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return ['lines_count' => $rows->sum('lines_count')];
    }
}
