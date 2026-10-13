<?php
declare(strict_types=1);
namespace App\Services\Scanning;

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\StockStatus;
use App\Models\Inventory\Lpn;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockTransaction;
use App\Models\Location;
use App\Models\User;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

final class RelocationService
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function moveLpn(Lpn $lpn, Location $destination, User $user, string $requestId): StockTransaction
    {
        return DB::transaction(function () use ($lpn, $destination, $user, $requestId) {
            $balances = StockBalance::where('lpn_id', $lpn->id)
                ->where('qty_on_hand', '>', 0)
                ->with('item')
                ->get();

            $lastTx = null;
            foreach ($balances as $balance) {
                $fromKey = new StockKey(
                    itemId:             $balance->item_id,
                    batchId:            $balance->batch_id,
                    lpnId:              $lpn->id,
                    locationId:         $balance->location_id,
                    virtualWarehouseId: $balance->virtual_warehouse_id,
                    stockStatus:        $balance->stock_status,
                );
                $ref = new StockReference(
                    referenceType: 'relocation',
                    referenceId:   null,
                    referenceNo:   $requestId,
                );
                $lastTx = $this->ledger->move(
                    from:           $fromKey,
                    toLocationId:   $destination->id,
                    qty:            (string) $balance->qty_on_hand,
                    uomId:          $balance->item->base_uom_id,
                    ref:            $ref,
                    idempotencyKey: "relocation:{$requestId}:lpn:{$lpn->id}:{$balance->id}",
                );
            }

            $lpn->update(['current_location_id' => $destination->id]);

            return $lastTx ?? throw new \DomainException("No stock found on LPN {$lpn->code}");
        });
    }

    public function moveItem(
        int $itemId,
        ?int $batchId,
        int $fromLocationId,
        int $toLocationId,
        int $virtualWarehouseId,
        string $qty,
        int $uomId,
        array $serials,
        User $user,
        string $requestId,
    ): StockTransaction {
        $fromKey = new StockKey(
            itemId:             $itemId,
            batchId:            $batchId,
            lpnId:              null,
            locationId:         $fromLocationId,
            virtualWarehouseId: $virtualWarehouseId,
            stockStatus:        StockStatus::Available,
        );
        $ref = new StockReference(
            referenceType: 'relocation',
            referenceId:   null,
            referenceNo:   $requestId,
        );
        return $this->ledger->move(
            from:           $fromKey,
            toLocationId:   $toLocationId,
            qty:            $qty,
            uomId:          $uomId,
            ref:            $ref,
            serials:        $serials,
            idempotencyKey: "relocation:{$requestId}:item:{$itemId}",
        );
    }
}
