<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\ReservationStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockReservation;
use Illuminate\Support\Facades\DB;

final class StockReservations
{
    // Creates a reservation and increments qty_allocated on the balance.
    public function reserve(StockBalance $balance, string $qty, string $refType, int $refId): StockReservation
    {
        return DB::transaction(function () use ($balance, $qty, $refType, $refId) {
            $balance->refresh()->lockForUpdate();

            $available = bcsub((string) $balance->qty_on_hand, (string) $balance->qty_allocated, 4);
            if (bccomp($available, $qty, 4) < 0) {
                throw new \DomainException('Insufficient available stock for reservation.');
            }

            DB::table('stock_balances')->where('id', $balance->id)->update([
                'qty_allocated' => bcadd((string) $balance->qty_allocated, $qty, 4),
                'updated_at'    => now(),
            ]);

            return StockReservation::create([
                'stock_balance_id' => $balance->id,
                'qty'              => $qty,
                'reference_type'   => $refType,
                'reference_id'     => $refId,
                'status'           => ReservationStatus::Active,
            ]);
        });
    }

    // Releases all or part of a reservation and decrements qty_allocated.
    public function release(StockReservation $reservation, ?string $qty = null): void
    {
        DB::transaction(function () use ($reservation, $qty) {
            $releaseQty = $qty ?? (string) $reservation->qty;

            if (bccomp($releaseQty, (string) $reservation->qty, 4) > 0) {
                throw new \InvalidArgumentException('Release quantity exceeds reservation quantity.');
            }

            $balance = StockBalance::where('id', $reservation->stock_balance_id)->lockForUpdate()->firstOrFail();

            $isFullRelease = bccomp($releaseQty, (string) $reservation->qty, 4) === 0;

            if ($isFullRelease) {
                $reservation->status = ReservationStatus::Released;
                $reservation->save();
            } else {
                // Partial release: reduce the reservation qty, keep status Active
                $reservation->qty = bcsub((string) $reservation->qty, $releaseQty, 4);
                $reservation->save();
            }

            $newAllocated = bcsub((string) $balance->qty_allocated, $releaseQty, 4);
            // Floor at zero to guard against any floating-point drift
            if (bccomp($newAllocated, '0', 4) < 0) {
                $newAllocated = '0.0000';
            }

            DB::table('stock_balances')->where('id', $balance->id)->update([
                'qty_allocated' => $newAllocated,
                'updated_at'    => now(),
            ]);
        });
    }
}
