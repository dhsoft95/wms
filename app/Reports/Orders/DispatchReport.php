<?php

declare(strict_types=1);

namespace App\Reports\Orders;

use App\Contracts\Reports\ReportDefinition;
use App\Models\DeliveryOrderLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DispatchReport implements ReportDefinition
{
    public function key(): string   { return 'dispatch'; }
    public function label(): string { return 'Dispatch'; }
    public function group(): string { return 'Orders'; }
    public function periodType(): string { return 'range'; }
    public function pdfEnabled(): bool   { return true; }

    public function columns(): array
    {
        return [
            ['key' => 'dispatched_at',                        'label' => 'Dispatched',    'align' => 'left',   'format' => 'date'],
            ['key' => 'deliveryOrder.number',                 'label' => 'DO No',         'align' => 'left',   'format' => 'text'],
            ['key' => 'deliveryOrder.consigneePartner.name',  'label' => 'Consignee',     'align' => 'left',   'format' => 'text'],
            ['key' => 'item.sku',                             'label' => 'SKU',           'align' => 'left',   'format' => 'text'],
            ['key' => 'item.name',                            'label' => 'Item',          'align' => 'left',   'format' => 'text'],
            ['key' => 'uom.code',                             'label' => 'UOM',           'align' => 'center', 'format' => 'text'],
            ['key' => 'requested_qty',                        'label' => 'Requested',     'align' => 'right',  'format' => 'number', 'total' => true],
            ['key' => 'dispatched_qty',                       'label' => 'Dispatched',    'align' => 'right',  'format' => 'number', 'total' => true],
            ['key' => 'short_qty',                            'label' => 'Short',         'align' => 'right',  'format' => 'number', 'total' => true],
            ['key' => 'status',                               'label' => 'Line Status',   'align' => 'center', 'format' => 'enum'],
        ];
    }

    public function groupBy(): string
    {
        return 'deliveryOrder.number';
    }

    public function query(array $filters, User $user): Builder
    {
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $filters['date_to']   ?? now()->format('Y-m-d');

        $query = DeliveryOrderLine::query()
            ->join('delivery_orders as do', 'do.id', '=', 'delivery_order_lines.delivery_order_id')
            ->join('dispatches', 'dispatches.delivery_order_id', '=', 'do.id')
            ->whereNull('do.deleted_at')
            ->whereBetween('dispatches.dispatched_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->selectRaw('delivery_order_lines.*, MIN(dispatches.dispatched_at) as dispatched_at')
            ->groupBy('delivery_order_lines.id')
            ->with(['deliveryOrder.consigneePartner', 'item', 'uom'])
            ->orderByRaw('MIN(dispatches.dispatched_at) desc')
            ->orderBy('do.id');

        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            $query->whereIn('do.virtual_warehouse_id', $vwIds);
        }

        if ($filters['warehouse_id'] ?? null) {
            $query->where('do.virtual_warehouse_id', $filters['warehouse_id']);
        }

        if ($filters['department_id'] ?? null) {
            $vwInDept = DB::table('virtual_warehouses')
                ->where('department_id', $filters['department_id'])
                ->pluck('id');
            $query->whereIn('do.virtual_warehouse_id', $vwInDept);
        }

        return $query;
    }

    public function summary(Collection $rows): array
    {
        $dos       = $rows->pluck('delivery_order_id')->unique()->count();
        $requested = $rows->sum('requested_qty');
        $dispatched = $rows->sum('dispatched_qty');
        $short     = $rows->sum('short_qty');

        return [
            'Orders'              => number_format($dos),
            'Lines'               => number_format($rows->count()),
            'Total Requested'     => number_format((float) $requested, 2),
            'Total Dispatched'    => number_format((float) $dispatched, 2),
            'Total Short'         => number_format((float) $short, 2),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return [
            'requested_qty'  => $rows->sum('requested_qty'),
            'dispatched_qty' => $rows->sum('dispatched_qty'),
            'short_qty'      => $rows->sum('short_qty'),
        ];
    }
}
