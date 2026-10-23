<?php

declare(strict_types=1);

namespace App\Reports\Stock;

use App\Contracts\Reports\ReportDefinition;
use App\Enums\TransferStatus;
use App\Models\StockTransferLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class StockInTransitReport implements ReportDefinition
{
    public function key(): string   { return 'stock-in-transit'; }
    public function label(): string { return 'Stock In Transit'; }
    public function group(): string { return 'Stock'; }
    public function periodType(): string { return 'as_of'; }
    public function pdfEnabled(): bool   { return false; }

    public function columns(): array
    {
        return [
            ['key' => 'transfer.number',                    'label' => 'Transfer No.',   'align' => 'left',  'format' => 'text'],
            ['key' => 'transfer.fromVirtualWarehouse.name', 'label' => 'From Warehouse', 'align' => 'left',  'format' => 'text'],
            ['key' => 'transfer.toVirtualWarehouse.name',   'label' => 'To Warehouse',   'align' => 'left',  'format' => 'text'],
            ['key' => 'item.sku',                           'label' => 'SKU',            'align' => 'left',  'format' => 'text'],
            ['key' => 'item.name',                          'label' => 'Item',           'align' => 'left',  'format' => 'text'],
            ['key' => 'qty',                                'label' => 'Qty',            'align' => 'right', 'format' => 'number'],
            ['key' => 'transfer.status',                    'label' => 'Status',         'align' => 'center','format' => 'enum'],
            ['key' => 'transfer.created_at',                'label' => 'Created',        'align' => 'left',  'format' => 'date'],
        ];
    }

    public function query(array $filters, User $user): Builder
    {
        $inTransitStatuses = [
            TransferStatus::Submitted->value,
            TransferStatus::Accepted->value,
            TransferStatus::Approved->value,
        ];

        $query = StockTransferLine::query()
            ->join('stock_transfers', 'stock_transfer_lines.stock_transfer_id', '=', 'stock_transfers.id')
            ->whereIn('stock_transfers.status', $inTransitStatuses)
            ->select('stock_transfer_lines.*')
            ->with(['item', 'transfer.fromVirtualWarehouse', 'transfer.toVirtualWarehouse']);

        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            $query->where(function ($q) use ($vwIds) {
                $q->whereIn('stock_transfers.from_virtual_warehouse_id', $vwIds)
                  ->orWhereIn('stock_transfers.to_virtual_warehouse_id', $vwIds);
            });
        }

        if ($filters['warehouse_id'] ?? null) {
            $wid = $filters['warehouse_id'];
            $query->where(function ($q) use ($wid) {
                $q->where('stock_transfers.from_virtual_warehouse_id', $wid)
                  ->orWhere('stock_transfers.to_virtual_warehouse_id', $wid);
            });
        }

        if ($filters['department_id'] ?? null) {
            $did = $filters['department_id'];
            $query->where(function ($q) use ($did) {
                $q->whereHas('transfer.fromVirtualWarehouse', fn ($q2) => $q2->where('department_id', $did))
                  ->orWhereHas('transfer.toVirtualWarehouse', fn ($q2) => $q2->where('department_id', $did));
            });
        }

        return $query->orderBy('stock_transfers.created_at', 'desc');
    }

    public function summary(Collection $rows): array
    {
        return [
            'Lines'     => $rows->count(),
            'Total Qty' => number_format((float) $rows->sum('qty'), 2),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return ['qty' => $rows->sum('qty')];
    }
}
