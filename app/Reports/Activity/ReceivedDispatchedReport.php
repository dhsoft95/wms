<?php

declare(strict_types=1);

namespace App\Reports\Activity;

use App\Contracts\Reports\ReportDefinition;
use App\Enums\Inventory\MovementType;
use App\Models\Inventory\StockTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ReceivedDispatchedReport implements ReportDefinition
{
    public function key(): string   { return 'received-dispatched'; }
    public function label(): string { return 'Received & Dispatched'; }
    public function group(): string { return 'Activity'; }
    public function periodType(): string { return 'range'; }
    public function pdfEnabled(): bool   { return false; }

    public function columns(): array
    {
        return [
            ['key' => 'period_label',   'label' => 'Period',       'align' => 'left',   'format' => 'text'],
            ['key' => 'direction',      'label' => 'Direction',    'align' => 'center', 'format' => 'text'],
            ['key' => 'movement_type',  'label' => 'Type',         'align' => 'left',   'format' => 'text'],
            ['key' => 'tx_count',       'label' => 'Transactions', 'align' => 'right',  'format' => 'number'],
            ['key' => 'total_qty',      'label' => 'Total Qty',    'align' => 'right',  'format' => 'number'],
        ];
    }

    public function query(array $filters, User $user): Builder
    {
        $dateFrom  = $filters['date_from']  ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo    = $filters['date_to']    ?? now()->format('Y-m-d');
        $docType   = $filters['doc_type']   ?? 'all';
        $direction = $filters['direction']  ?? 'both';
        $grouping  = $filters['grouping']   ?? 'day';

        $periodExpr = match ($grouping) {
            'week'  => "DATE_FORMAT(st.posted_at, '%Y-W%v')",
            'month' => "DATE_FORMAT(st.posted_at, '%Y-%m')",
            default => "DATE_FORMAT(st.posted_at, '%Y-%m-%d')",
        };

        $inTypes  = [MovementType::Receipt->value, MovementType::Putaway->value];
        $outTypes = [MovementType::Dispatch->value, MovementType::Pick->value];

        $allowedTypes = match ($docType) {
            'po'  => $inTypes,
            'mrn' => $outTypes,
            'stn' => [MovementType::OwnershipTransfer->value],
            default => array_merge($inTypes, $outTypes, [MovementType::OwnershipTransfer->value]),
        };

        $directionExpr = "CASE
            WHEN sm.qty > 0 THEN 'In'
            ELSE 'Out'
        END";

        $query = StockTransaction::query()
            ->from('stock_transactions as st')
            ->join('stock_movements as sm', 'sm.stock_transaction_id', '=', 'st.id')
            ->where('st.posted_at', '>=', $dateFrom)
            ->where('st.posted_at', '<=', $dateTo . ' 23:59:59')
            ->whereIn('st.type', $allowedTypes)
            ->select(
                DB::raw("{$periodExpr} as period_label"),
                DB::raw("{$directionExpr} as direction"),
                'st.type as movement_type',
                DB::raw('COUNT(DISTINCT st.id) as tx_count'),
                DB::raw('SUM(ABS(sm.qty)) as total_qty'),
            )
            ->groupBy(DB::raw($periodExpr), DB::raw($directionExpr), 'st.type')
            ->orderBy(DB::raw($periodExpr), 'desc');

        if ($direction === 'in') {
            $query->where('sm.qty', '>', 0);
        } elseif ($direction === 'out') {
            $query->where('sm.qty', '<', 0);
        }

        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            $query->whereIn('sm.virtual_warehouse_id', $vwIds);
        }

        if ($filters['warehouse_id'] ?? null) {
            $query->where('sm.virtual_warehouse_id', $filters['warehouse_id']);
        }

        if ($filters['department_id'] ?? null) {
            $vwInDept = DB::table('virtual_warehouses')
                ->where('department_id', $filters['department_id'])
                ->pluck('id');
            $query->whereIn('sm.virtual_warehouse_id', $vwInDept);
        }

        return $query;
    }

    public function summary(Collection $rows): array
    {
        $inTotal  = $rows->where('direction', 'In')->sum('total_qty');
        $outTotal = $rows->where('direction', 'Out')->sum('total_qty');
        return [
            'Transactions' => (string) (int) $rows->sum('tx_count'),
            'Total In'     => number_format((float) $inTotal, 2),
            'Total Out'    => number_format((float) $outTotal, 2),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return [
            'tx_count'  => $rows->sum('tx_count'),
            'total_qty' => $rows->sum('total_qty'),
        ];
    }
}
