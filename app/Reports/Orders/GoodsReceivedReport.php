<?php

declare(strict_types=1);

namespace App\Reports\Orders;

use App\Contracts\Reports\ReportDefinition;
use App\Enums\GrnStatus;
use App\Models\GrnLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class GoodsReceivedReport implements ReportDefinition
{
    public function key(): string   { return 'goods-received'; }
    public function label(): string { return 'Goods Received'; }
    public function group(): string { return 'Orders'; }
    public function periodType(): string { return 'range'; }
    public function pdfEnabled(): bool   { return true; }

    public function columns(): array
    {
        return [
            ['key' => 'grn.confirmed_at',              'label' => 'Date',              'align' => 'left',   'format' => 'date'],
            ['key' => 'grn.number',                    'label' => 'GRN No',            'align' => 'left',   'format' => 'text'],
            ['key' => 'grn.purchaseOrder.number',      'label' => 'PO No',             'align' => 'left',   'format' => 'text'],
            ['key' => 'grn.supplierPartner.name',      'label' => 'Supplier',          'align' => 'left',   'format' => 'text'],
            ['key' => 'item.sku',                      'label' => 'SKU',               'align' => 'left',   'format' => 'text'],
            ['key' => 'item.name',                     'label' => 'Item',              'align' => 'left',   'format' => 'text'],
            ['key' => 'uom.code',                      'label' => 'UOM',               'align' => 'center', 'format' => 'text'],
            ['key' => 'expected_qty',                  'label' => 'Expected',          'align' => 'right',  'format' => 'number', 'total' => true],
            ['key' => 'received_good_qty',             'label' => 'Received Good',     'align' => 'right',  'format' => 'number', 'total' => true],
            ['key' => 'received_damaged_qty',          'label' => 'Damaged',           'align' => 'right',  'format' => 'number', 'total' => true],
            ['key' => 'variance_qty',                  'label' => 'Variance',          'align' => 'right',  'format' => 'signed', 'tone' => 'signed', 'total' => true],
            ['key' => 'variance_type',                 'label' => 'Variance Type',     'align' => 'center', 'format' => 'enum'],
        ];
    }

    public function groupBy(): string
    {
        return 'grn.number';
    }

    public function query(array $filters, User $user): Builder
    {
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $filters['date_to']   ?? now()->format('Y-m-d');

        $terminal = [GrnStatus::Cancelled->value];

        $query = GrnLine::query()
            ->join('grns', 'grns.id', '=', 'grn_lines.grn_id')
            ->whereNotIn('grns.status', $terminal)
            ->whereNotNull('grns.confirmed_at')
            ->whereBetween('grns.confirmed_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->select('grn_lines.*')
            ->with(['grn.purchaseOrder', 'grn.supplierPartner', 'item', 'uom'])
            ->orderBy('grns.confirmed_at', 'desc')
            ->orderBy('grns.id');

        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            $query->whereIn('grn_lines.virtual_warehouse_id', $vwIds);
        }

        if ($filters['warehouse_id'] ?? null) {
            $query->where('grn_lines.virtual_warehouse_id', $filters['warehouse_id']);
        }

        if ($filters['department_id'] ?? null) {
            $vwInDept = DB::table('virtual_warehouses')
                ->where('department_id', $filters['department_id'])
                ->pluck('id');
            $query->whereIn('grn_lines.virtual_warehouse_id', $vwInDept);
        }

        return $query;
    }

    public function summary(Collection $rows): array
    {
        $grns     = $rows->pluck('grn_id')->unique()->count();
        $expected = $rows->sum('expected_qty');
        $received = $rows->sum(fn ($r) => (float) $r->received_good_qty + (float) $r->received_damaged_qty);
        $variance = $rows->sum('variance_qty');

        return [
            'GRNs'           => number_format($grns),
            'Lines'          => number_format($rows->count()),
            'Total Expected' => number_format((float) $expected, 2),
            'Total Received' => number_format((float) $received, 2),
            'Net Variance'   => number_format((float) $variance, 2),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return [
            'expected_qty'         => $rows->sum('expected_qty'),
            'received_good_qty'    => $rows->sum('received_good_qty'),
            'received_damaged_qty' => $rows->sum('received_damaged_qty'),
            'variance_qty'         => $rows->sum('variance_qty'),
        ];
    }
}
