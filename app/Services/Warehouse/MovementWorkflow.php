<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockPosting;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\MovementStatus;
use App\Models\InternalMovement;
use App\Models\Inventory\Batch;
use App\Models\Inventory\StockBalance;
use App\Models\Location;
use App\Models\User;
use App\Services\Foundation\DocumentNumber;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

final class MovementWorkflow
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function create(array $data, User $user): InternalMovement
    {
        return DB::transaction(function () use ($data, $user) {
            $movement = InternalMovement::create([
                'number'               => DocumentNumber::next('MOV'),
                'virtual_warehouse_id' => $data['virtual_warehouse_id'],
                'notes'                => $data['notes'] ?? null,
                'created_by'           => $user->id,
                'status'               => MovementStatus::Draft,
            ]);

            foreach ($data['lines'] as $lineData) {
                $line = $movement->lines()->create([
                    'item_id'          => (int) $lineData['item_id'],
                    'from_location_id' => (int) $lineData['from_location_id'],
                    'to_location_id'   => (int) $lineData['to_location_id'],
                    'batch_no'         => $lineData['batch_no'] ?: null,
                    'expiry_date'      => $lineData['expiry_date'] ?: null,
                    'qty'              => $lineData['qty'],
                    'uom_id'           => (int) $lineData['uom_id'],
                ]);

                if (! empty($lineData['serials'])) {
                    foreach ($lineData['serials'] as $sn) {
                        $sn = trim((string) $sn);
                        if ($sn !== '') {
                            $line->serials()->create(['serial_number' => $sn]);
                        }
                    }
                }
            }

            return $movement->load('lines.item', 'lines.fromLocation', 'lines.toLocation', 'lines.serials', 'virtualWarehouse');
        });
    }

    public function post(InternalMovement $movement, User $user): InternalMovement
    {
        return DB::transaction(function () use ($movement, $user) {
            // Re-fetch with lock — guarantees single-post and prevents race conditions
            $movement = InternalMovement::where('id', $movement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($movement->status !== MovementStatus::Draft) {
                throw new \DomainException('Movement has already been posted or cancelled.');
            }

            $movement->load('lines.item', 'lines.fromLocation', 'lines.toLocation', 'lines.serials');

            $ref = new StockReference(
                referenceType: InternalMovement::class,
                referenceId: $movement->id,
                referenceNo: $movement->number,
            );

            $allLines = [];

            foreach ($movement->lines as $line) {
                $this->assertLocationValid($line->fromLocation, 'source');
                $this->assertLocationValid($line->toLocation, 'destination');

                if ($line->from_location_id === $line->to_location_id) {
                    throw new \DomainException(
                        "Source and destination bins must be different for item {$line->item?->sku}."
                    );
                }

                // Resolve batch_no → batch_id
                $batchId = null;
                if ($line->batch_no !== null) {
                    $batch = Batch::where('item_id', $line->item_id)
                        ->where('batch_no', $line->batch_no)
                        ->first();

                    if ($batch === null) {
                        throw new \DomainException(
                            "Batch '{$line->batch_no}' not found for item {$line->item?->sku}."
                        );
                    }

                    $batchId = $batch->id;
                }

                // Re-check available qty with lock
                $fromKey = new StockKey(
                    itemId: $line->item_id,
                    batchId: $batchId,
                    lpnId: null,
                    locationId: $line->from_location_id,
                    virtualWarehouseId: $movement->virtual_warehouse_id,
                    stockStatus: StockStatus::Available,
                );

                $balance = StockBalance::where('key_hash', $fromKey->hash())
                    ->lockForUpdate()
                    ->first();

                $available = $balance ? $balance->qtyAvailable() : '0.0000';

                if (bccomp($available, (string) $line->qty, 4) < 0) {
                    throw new \DomainException(
                        "Insufficient stock for {$line->item?->sku} in {$line->fromLocation?->code}."
                        . " Available: {$available}, required: {$line->qty}."
                    );
                }

                $serials = $line->serials->pluck('serial_number')->all();

                $allLines[] = new StockLine(
                    itemId: $line->item_id,
                    locationId: $line->from_location_id,
                    virtualWarehouseId: $movement->virtual_warehouse_id,
                    stockStatus: StockStatus::Available,
                    enteredQty: bcsub('0', (string) $line->qty, 4),
                    enteredUomId: $line->uom_id,
                    batchId: $batchId,
                    serials: $serials,
                );

                $allLines[] = new StockLine(
                    itemId: $line->item_id,
                    locationId: $line->to_location_id,
                    virtualWarehouseId: $movement->virtual_warehouse_id,
                    stockStatus: StockStatus::Available,
                    enteredQty: (string) $line->qty,
                    enteredUomId: $line->uom_id,
                    batchId: $batchId,
                    serials: $serials,
                );
            }

            // Single atomic ledger call — one idempotency key for the whole document
            $this->ledger->post(new StockPosting(
                type: MovementType::Relocation,
                reference: $ref,
                lines: $allLines,
                idempotencyKey: "mov:{$movement->id}:post",
            ));

            $movement->update([
                'status'    => MovementStatus::Completed,
                'posted_at' => now(),
            ]);

            activity()
                ->performedOn($movement)
                ->causedBy($user)
                ->log('Internal movement posted');

            return $movement->fresh();
        });
    }

    public function cancel(InternalMovement $movement, User $user): InternalMovement
    {
        if ($movement->status !== MovementStatus::Draft) {
            throw new \DomainException('Only draft movements can be cancelled.');
        }

        $movement->update(['status' => MovementStatus::Cancelled]);

        activity()
            ->performedOn($movement)
            ->causedBy($user)
            ->log('Internal movement cancelled');

        return $movement->fresh();
    }

    private function assertLocationValid(Location $location, string $role): void
    {
        if (! $location->isBin()) {
            throw new \DomainException("The {$role} location '{$location->code}' must be a bin.");
        }

        if ($location->status !== LocationStatus::Active) {
            throw new \DomainException(
                "The {$role} location '{$location->code}' is not active (status: {$location->status->value})."
            );
        }

        if ($location->type === LocationType::Quarantine) {
            throw new \DomainException(
                "The {$role} location '{$location->code}' is a quarantine location and cannot be used for movements."
            );
        }
    }
}
