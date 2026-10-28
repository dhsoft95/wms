<?php

declare(strict_types=1);

namespace App\Reports\Activity;

use App\Contracts\Reports\ReportDefinition;
use App\Enums\Inventory\MovementType;
use App\Models\Inventory\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class StockMovementReport implements ReportDefinition
{
    public function key(): string   { return 'stock-movement'; }
    public function label(): string { return 'Stock Movements'; }
    public function group(): string { return 'Activity'; }
    public function periodType(): string { return 'range'; }
    public function pdfEnabled(): bool   { return true; }

    public function columns(): array
    {
        return [
            ['key' => 'transaction.posted_at',   'label' => 'Date',      'align' => 'left',   'format' => 'datetime'],
            ['key' => 'transaction.number',       'label' => 'Tx No',     'align' => 'left',   'format' => 'text'],
            ['key' => 'display_type',             'label' => 'Type',      'align' => 'left',   'format' => 'text'],
            ['key' => 'transaction.reference_no', 'label' => 'Reference', 'align' => 'left',   'format' => 'text'],
            ['key' => 'item.sku',                 'label' => 'SKU',       'align' => 'left',   'format' => 'text'],
            ['key' => 'item.name',                'label' => 'Item',      'align' => 'left',   'format' => 'text'],
            ['key' => 'virtualWarehouse.name',    'label' => 'Warehouse', 'align' => 'left',   'format' => 'text'],
            ['key' => 'location.code',            'label' => 'Location',  'align' => 'left',   'format' => 'text'],
            ['key' => 'qty',                      'label' => 'Qty',       'align' => 'right',  'format' => 'signed', 'tone' => 'signed', 'total' => true],
            ['key' => 'enteredUom.code',          'label' => 'UOM',       'align' => 'center', 'format' => 'text'],
        ];
    }

    public function query(array $filters, User $user): Builder
    {
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $filters['date_to']   ?? now()->format('Y-m-d');

        $excluded = [
            MovementType::Reversal->value,
            MovementType::SerialCapture->value,
        ];

        $typeLabel = "CASE st.type
            WHEN 'receipt'            THEN 'Receipt'
            WHEN 'putaway'            THEN 'Putaway'
            WHEN 'relocation'         THEN CASE WHEN st.reference_type = 'putaway_task' THEN 'Putaway' ELSE 'Internal Transfer' END
            WHEN 'pick'               THEN 'Pick'
            WHEN 'dispatch'           THEN 'Dispatch'
            WHEN 'ownership_transfer' THEN 'W2W Transfer'
            WHEN 'adjustment'         THEN 'Adjustment'
            WHEN 'count_adjustment'   THEN 'Count Adjustment'
            WHEN 'hold'               THEN 'Hold'
            WHEN 'release'            THEN 'Release'
            WHEN 'opening_balance'    THEN 'Opening Balance'
            WHEN 'obsolete'           THEN 'Mark Obsolete'
            WHEN 'disposal'           THEN 'Disposal'
            ELSE st.type
        END";

        $query = StockMovement::withoutGlobalScopes()
            ->join('stock_transactions as st', 'st.id', '=', 'stock_movements.stock_transaction_id')
            ->whereNotIn('st.type', $excluded)
            ->where('st.posted_at', '>=', $dateFrom . ' 00:00:00')
            ->where('st.posted_at', '<=', $dateTo . ' 23:59:59')
            ->selectRaw("stock_movements.*, ({$typeLabel}) as display_type")
            ->with(['transaction', 'item', 'location', 'virtualWarehouse', 'enteredUom'])
            ->orderBy('st.posted_at', 'desc')
            ->orderBy('st.id', 'desc');

        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            $query->whereIn('stock_movements.virtual_warehouse_id', $vwIds);
        }

        if ($filters['warehouse_id'] ?? null) {
            $query->where('stock_movements.virtual_warehouse_id', $filters['warehouse_id']);
        }

        if ($filters['department_id'] ?? null) {
            $query->whereHas('virtualWarehouse', fn ($q) => $q->where('department_id', $filters['department_id']));
        }

        return $query;
    }

    public function summary(Collection $rows): array
    {
        $received  = $rows->filter(fn ($r) => $r->transaction?->type === MovementType::Receipt)->sum('qty');
        $dispatched = $rows->filter(fn ($r) => $r->transaction?->type === MovementType::Dispatch)->sum('qty');

        $internalTypes = [
            MovementType::Putaway,
            MovementType::Relocation,
            MovementType::OwnershipTransfer,
            MovementType::Pick,
        ];
        $internalMoves = $rows
            ->filter(fn ($r) => in_array($r->transaction?->type, $internalTypes, true) && (float) $r->qty > 0)
            ->sum('qty');

        $net = $rows->sum('qty');

        return [
            'Received'        => number_format((float) $received, 2),
            'Dispatched'      => number_format(abs((float) $dispatched), 2),
            'Internal Moves'  => number_format((float) $internalMoves, 2),
            'Net Change'      => number_format((float) $net, 2),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return [
            'qty' => $rows->sum('qty'),
        ];
    }
}
