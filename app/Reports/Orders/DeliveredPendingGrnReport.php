<?php

declare(strict_types=1);

namespace App\Reports\Orders;

use App\Contracts\Reports\ReportDefinition;
use App\Enums\GrnStatus;
use App\Models\Grn;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class DeliveredPendingGrnReport implements ReportDefinition
{
    public function key(): string   { return 'delivered-pending-grn'; }
    public function label(): string { return 'Delivered & Pending GRN'; }
    public function group(): string { return 'Orders'; }
    public function periodType(): string { return 'range'; }
    public function pdfEnabled(): bool   { return false; }

    public function columns(): array
    {
        return [
            ['key' => 'number',                'label' => 'GRN No.',     'align' => 'left',   'format' => 'text'],
            ['key' => 'purchaseOrder.number',  'label' => 'PO No.',      'align' => 'left',   'format' => 'text'],
            ['key' => 'supplierPartner.name',   'label' => 'Supplier',    'align' => 'left',   'format' => 'text'],
            ['key' => 'warehouse.name',        'label' => 'Warehouse',   'align' => 'left',   'format' => 'text'],
            ['key' => 'status',                'label' => 'Status',      'align' => 'center', 'format' => 'enum'],
            ['key' => 'lines_count',           'label' => 'Lines',       'align' => 'right',  'format' => 'number'],
            ['key' => 'arrival_datetime',      'label' => 'Arrived',     'align' => 'left',   'format' => 'date'],
            ['key' => 'created_at',            'label' => 'Created',     'align' => 'left',   'format' => 'date'],
        ];
    }

    public function query(array $filters, User $user): Builder
    {
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $filters['date_to']   ?? now()->format('Y-m-d');

        $terminalStatuses = [GrnStatus::Closed->value, GrnStatus::Cancelled->value];

        $query = Grn::query()
            ->whereNotIn('status', $terminalStatuses)
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->withCount('lines')
            ->with(['purchaseOrder', 'supplierPartner', 'warehouse']);

        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            $query->whereHas('purchaseOrder', fn ($q) => $q->whereIn('virtual_warehouse_id', $vwIds));
        }

        if ($filters['warehouse_id'] ?? null) {
            $query->whereHas('purchaseOrder', fn ($q) => $q->where('virtual_warehouse_id', $filters['warehouse_id']));
        }

        if ($filters['department_id'] ?? null) {
            $query->whereHas('purchaseOrder', fn ($q) => $q->where('department_id', $filters['department_id']));
        }

        return $query->latest('created_at');
    }

    public function summary(Collection $rows): array
    {
        return [
            'GRNs'  => $rows->count(),
            'Lines' => (string) (int) $rows->sum('lines_count'),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return ['lines_count' => $rows->sum('lines_count')];
    }
}
