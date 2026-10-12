<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class StockQueryService
{
    // Stock on Hand query with optional filters; respects VW access scope.
    public function stockOnHand(
        ?int $itemId = null,
        ?int $locationId = null,
        ?int $virtualWarehouseId = null,
        ?int $batchId = null,
        ?string $stockStatus = null,
        ?int $expiringWithinDays = null,
        bool $groupByItem = false,
        ?User $user = null,
        int $perPage = 25,
        ?string $search = null,
        ?int $categoryId = null,
    ): LengthAwarePaginator {
        $query = StockBalance::with(['item', 'batch', 'lpn', 'location', 'virtualWarehouse'])
            ->where('qty_on_hand', '>', 0);

        if ($itemId !== null) {
            $query->where('item_id', $itemId);
        }

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        if ($virtualWarehouseId !== null) {
            $query->where('virtual_warehouse_id', $virtualWarehouseId);
        }

        if ($batchId !== null) {
            $query->where('batch_id', $batchId);
        }

        if ($stockStatus !== null) {
            $query->where('stock_status', $stockStatus);
        }

        if ($expiringWithinDays !== null) {
            $cutoff = now()->addDays($expiringWithinDays)->toDateString();
            $query->whereHas('batch', fn ($q) => $q->whereNotNull('expiry_date')->where('expiry_date', '<=', $cutoff));
        }

        if ($search !== null) {
            $query->whereHas('item', fn ($q) =>
                $q->where('sku', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")
            );
        }

        if ($categoryId !== null) {
            $query->whereHas('item', fn ($q) => $q->where('category_id', $categoryId));
        }

        // VW scope: department-users can only see their assigned VWs
        if ($user !== null && ! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            if (! empty($vwIds)) {
                $query->whereIn('virtual_warehouse_id', $vwIds);
            }
        }

        if ($groupByItem) {
            $query->selectRaw('item_id, SUM(qty_on_hand) as qty_on_hand, SUM(qty_allocated) as qty_allocated')
                ->groupBy('item_id');
        }

        return $query->orderBy('id')->paginate($perPage);
    }

    // Summary stats for the Stock on Hand screen.
    public function stockStats(?User $user = null): array
    {
        $base = StockBalance::where('qty_on_hand', '>', 0);

        if ($user !== null && ! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            $base->whereIn('virtual_warehouse_id', $vwIds);
        }

        return [
            'total_skus'      => (clone $base)->distinct('item_id')->count('item_id'),
            'total_on_hand'   => (clone $base)->sum('qty_on_hand'),
            'total_allocated' => (clone $base)->sum('qty_allocated'),
            'below_min'       => (clone $base)->whereHas('item', fn ($q) =>
                $q->whereNotNull('min_stock')
                  ->whereColumn('min_stock', '>', \Illuminate\Support\Facades\DB::raw(
                      '(SELECT SUM(qty_on_hand) FROM stock_balances sb2 WHERE sb2.item_id = stock_balances.item_id)'
                  ))
            )->distinct('item_id')->count('item_id'),
        ];
    }

    // Paginated transactions list with optional filters; respects VW access scope.
    public function transactionsPage(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $movementType = null,
        ?string $referenceNo = null,
        ?int $postedBy = null,
        ?int $virtualWarehouseId = null,
        ?int $itemId = null,
        ?int $locationId = null,
        ?User $scopedUser = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $query = StockTransaction::with(['movements.item', 'movements.location', 'movements.virtualWarehouse', 'postedBy'])
            ->when($dateFrom, fn ($q) => $q->where('posted_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('posted_at', '<=', $dateTo . ' 23:59:59'))
            ->when($movementType, fn ($q) => $q->where('type', $movementType))
            ->when($referenceNo, fn ($q) => $q->where('reference_no', 'like', "%{$referenceNo}%"))
            ->when($postedBy, fn ($q) => $q->where('posted_by', $postedBy));

        if ($itemId || $locationId || $virtualWarehouseId || ($scopedUser && ! $scopedUser->canAccessAllVirtualWarehouses())) {
            $query->whereHas('movements', function ($mq) use ($itemId, $locationId, $virtualWarehouseId, $scopedUser) {
                $mq->when($itemId, fn ($q) => $q->where('item_id', $itemId))
                   ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
                   ->when($virtualWarehouseId, fn ($q) => $q->where('virtual_warehouse_id', $virtualWarehouseId));
                if ($scopedUser && ! $scopedUser->canAccessAllVirtualWarehouses()) {
                    $vwIds = $scopedUser->accessibleVirtualWarehouseIds();
                    $mq->whereIn('virtual_warehouse_id', $vwIds);
                }
            });
        }

        return $query->latest('posted_at')->paginate($perPage);
    }

    // Movement history query with optional filters; respects VW access scope.
    public function movements(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $movementType = null,
        ?int $itemId = null,
        ?int $locationId = null,
        ?int $virtualWarehouseId = null,
        ?string $referenceNo = null,
        ?int $userId = null,
        ?User $scopedUser = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $query = StockMovement::with(['transaction', 'item', 'batch', 'location', 'enteredUom'])
            ->join('stock_transactions', 'stock_movements.stock_transaction_id', '=', 'stock_transactions.id')
            ->select('stock_movements.*');

        if ($dateFrom !== null) {
            $query->where('stock_transactions.posted_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('stock_transactions.posted_at', '<=', $dateTo . ' 23:59:59');
        }

        if ($movementType !== null) {
            $query->where('stock_transactions.type', $movementType);
        }

        if ($itemId !== null) {
            $query->where('stock_movements.item_id', $itemId);
        }

        if ($locationId !== null) {
            $query->where('stock_movements.location_id', $locationId);
        }

        if ($virtualWarehouseId !== null) {
            $query->where('stock_movements.virtual_warehouse_id', $virtualWarehouseId);
        }

        if ($referenceNo !== null) {
            $query->where('stock_transactions.reference_no', 'like', "%{$referenceNo}%");
        }

        if ($userId !== null) {
            $query->where('stock_transactions.posted_by', $userId);
        }

        // VW scope
        if ($scopedUser !== null && ! $scopedUser->canAccessAllVirtualWarehouses()) {
            $vwIds = $scopedUser->accessibleVirtualWarehouseIds();
            if (! empty($vwIds)) {
                $query->whereIn('stock_movements.virtual_warehouse_id', $vwIds);
            }
        }

        return $query->orderBy('stock_movements.id', 'desc')->paginate($perPage);
    }

    // Last N movements for an item (used in Item detail Stock tab).
    public function recentMovementsForItem(int $itemId, int $limit = 20): Collection
    {
        return StockMovement::with(['transaction', 'batch', 'location', 'enteredUom'])
            ->where('item_id', $itemId)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }

    // All balances for a specific location.
    public function balancesForLocation(int $locationId): Collection
    {
        return StockBalance::with(['item', 'batch', 'lpn', 'virtualWarehouse'])
            ->where('location_id', $locationId)
            ->where('qty_on_hand', '>', 0)
            ->orderBy('item_id')
            ->get();
    }

    // All balances for a specific item.
    public function balancesForItem(int $itemId): Collection
    {
        return StockBalance::with(['batch', 'lpn', 'location', 'virtualWarehouse'])
            ->where('item_id', $itemId)
            ->where('qty_on_hand', '>', 0)
            ->orderBy('location_id')
            ->get();
    }

    // Single transaction with all movements loaded; returns null if out of VW scope.
    public function transactionWithLines(int $transactionId, ?User $scopedUser = null): ?StockTransaction
    {
        $transaction = StockTransaction::with(['movements.item', 'movements.batch', 'movements.location', 'movements.enteredUom', 'movements.serials', 'postedBy'])
            ->find($transactionId);

        if ($transaction === null) {
            return null;
        }

        // If the user is VW-restricted, verify every movement is within scope
        if ($scopedUser !== null && ! $scopedUser->canAccessAllVirtualWarehouses()) {
            $vwIds = $scopedUser->accessibleVirtualWarehouseIds();
            if (! empty($vwIds)) {
                foreach ($transaction->movements as $movement) {
                    if (! in_array($movement->virtual_warehouse_id, $vwIds, strict: true)) {
                        return null;
                    }
                }
            }
        }

        return $transaction;
    }
}
