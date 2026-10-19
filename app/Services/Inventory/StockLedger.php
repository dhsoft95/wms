<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockPosting;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\ReservationStatus;
use App\Enums\Inventory\SerialStatus;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Events\Inventory\StockPosted;
use App\Models\Inventory\Batch;
use App\Models\Inventory\SerialNumber;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementSerial;
use App\Models\Inventory\StockReservation;
use App\Models\Inventory\StockTransaction;
use App\Models\Item;
use App\Models\Location;
use App\Models\VirtualWarehouse;
use App\Models\User;
use App\Services\Foundation\DocumentNumber;
use App\Services\Inventory\StockLineValidator;
use App\Services\Foundation\UomConverter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class StockLedger
{
    // Single entry point for all stock changes; all helpers delegate here.
    public function post(StockPosting $posting, ?User $user = null): StockTransaction
    {
        return DB::transaction(function () use ($posting, $user) {
            // 1. Idempotency check
            if ($posting->idempotencyKey !== null) {
                $existing = StockTransaction::where('idempotency_key', $posting->idempotencyKey)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            // 2. Net-zero validation for paired movement types
            if ($posting->type->isPaired()) {
                $this->assertNetZero($posting);
            }

            // 3. Generate document number
            $number = DocumentNumber::next('STK');

            // Opening balance self-references its own STK number; resolve before insert.
            $referenceNo = ($posting->type === MovementType::OpeningBalance && $posting->reference->referenceNo === null)
                ? $number
                : $posting->reference->referenceNo;

            // 4. Create StockTransaction record
            $transaction = StockTransaction::create([
                'number'          => $number,
                'type'            => $posting->type,
                'reference_type'  => $posting->reference->referenceType,
                'reference_id'    => $posting->reference->referenceId,
                'reference_no'    => $referenceNo,
                'idempotency_key' => $posting->idempotencyKey,
                'notes'           => $posting->notes,
                'posted_by'       => $user?->id,
                'device'          => $posting->device,
                'posted_at'       => now(),
            ]);

            // 5. Process each StockLine
            // Pre-collect serials from outbound lines so paired inbound can accept them
            $releasedInThisPost = [];
            foreach ($posting->lines as $line) {
                if (! empty($line->serials) && bccomp(UomConverter::toBase(
                    Item::findOrFail($line->itemId), $line->enteredUomId, $line->enteredQty
                ), '0', 4) < 0) {
                    foreach ($line->serials as $sn) {
                        $releasedInThisPost[] = strtoupper(trim($sn));
                    }
                }
            }

            foreach ($posting->lines as $line) {
                $this->processLine($transaction, $line, $releasedInThisPost);
            }

            // 6. Fire event after all writes succeed
            event(new StockPosted($transaction));

            return $transaction;
        });
    }

    public function receive(StockLine $line, StockReference $ref, ?string $idempotencyKey = null): StockTransaction
    {
        return $this->post(new StockPosting(MovementType::Receipt, $ref, [$line], $idempotencyKey));
    }

    public function move(StockKey $from, int $toLocationId, string $qty, int $uomId, StockReference $ref, array $serials = [], ?string $idempotencyKey = null): StockTransaction
    {
        $outLine = new StockLine(
            itemId: $from->itemId,
            locationId: $from->locationId,
            virtualWarehouseId: $from->virtualWarehouseId,
            stockStatus: $from->stockStatus,
            enteredQty: bcsub('0', $qty, 4),
            enteredUomId: $uomId,
            batchId: $from->batchId,
            lpnId: $from->lpnId,
            serials: $serials,
        );

        $inLine = new StockLine(
            itemId: $from->itemId,
            locationId: $toLocationId,
            virtualWarehouseId: $from->virtualWarehouseId,
            stockStatus: $from->stockStatus,
            enteredQty: $qty,
            enteredUomId: $uomId,
            batchId: $from->batchId,
            lpnId: $from->lpnId,
            serials: $serials,
        );

        return $this->post(new StockPosting(MovementType::Relocation, $ref, [$outLine, $inLine], $idempotencyKey));
    }

    public function changeOwner(StockKey $from, int $toVwId, string $qty, int $uomId, StockReference $ref, array $serials = [], ?string $idempotencyKey = null): StockTransaction
    {
        $outLine = new StockLine(
            itemId: $from->itemId,
            locationId: $from->locationId,
            virtualWarehouseId: $from->virtualWarehouseId,
            stockStatus: $from->stockStatus,
            enteredQty: bcsub('0', $qty, 4),
            enteredUomId: $uomId,
            batchId: $from->batchId,
            lpnId: $from->lpnId,
            serials: $serials,
        );

        $inLine = new StockLine(
            itemId: $from->itemId,
            locationId: $from->locationId,
            virtualWarehouseId: $toVwId,
            stockStatus: $from->stockStatus,
            enteredQty: $qty,
            enteredUomId: $uomId,
            batchId: $from->batchId,
            lpnId: $from->lpnId,
            serials: $serials,
        );

        return $this->post(new StockPosting(MovementType::OwnershipTransfer, $ref, [$outLine, $inLine], $idempotencyKey));
    }

    public function changeStatus(StockKey $from, StockStatus $to, string $qty, int $uomId, StockReference $ref, array $serials = [], ?string $idempotencyKey = null): StockTransaction
    {
        $outLine = new StockLine(
            itemId: $from->itemId,
            locationId: $from->locationId,
            virtualWarehouseId: $from->virtualWarehouseId,
            stockStatus: $from->stockStatus,
            enteredQty: bcsub('0', $qty, 4),
            enteredUomId: $uomId,
            batchId: $from->batchId,
            lpnId: $from->lpnId,
            serials: $serials,
        );

        $inLine = new StockLine(
            itemId: $from->itemId,
            locationId: $from->locationId,
            virtualWarehouseId: $from->virtualWarehouseId,
            stockStatus: $to,
            enteredQty: $qty,
            enteredUomId: $uomId,
            batchId: $from->batchId,
            lpnId: $from->lpnId,
            serials: $serials,
        );

        $type = $to === StockStatus::OnHold ? MovementType::Hold : MovementType::Release;

        return $this->post(new StockPosting($type, $ref, [$outLine, $inLine], $idempotencyKey));
    }

    public function adjust(StockLine $line, StockReference $ref, ?string $idempotencyKey = null): StockTransaction
    {
        return $this->post(new StockPosting(MovementType::Adjustment, $ref, [$line], $idempotencyKey));
    }

    public function openingBalance(array $lines, ?string $notes = null, ?string $idempotencyKey = null): StockTransaction
    {
        return $this->post(new StockPosting(
            MovementType::OpeningBalance,
            new StockReference(),
            $lines,
            $idempotencyKey,
            $notes
        ));
    }

    /**
     * Attach serial numbers to an existing stock balance without changing qty.
     * Used when serials were not captured at receiving time (serials_missing flow).
     */
    public function attachSerials(StockKey $key, array $serials, StockReference $ref, ?User $user = null, ?string $idempotencyKey = null): StockTransaction
    {
        if (empty($serials)) {
            throw new \InvalidArgumentException('At least one serial number must be provided.');
        }

        return DB::transaction(function () use ($key, $serials, $ref, $user, $idempotencyKey) {
            if ($idempotencyKey !== null) {
                $existing = StockTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            $item = Item::findOrFail($key->itemId);
            if (! $item->track_serial) {
                throw new \InvalidArgumentException("Item {$item->sku} does not use serial tracking.");
            }

            $normalized = [];
            foreach ($serials as $rawSerial) {
                $s = SerialNumber::normalize((string) $rawSerial);
                if ($s === '') {
                    throw new \InvalidArgumentException("Serial number '{$rawSerial}' is invalid.");
                }
                $normalized[] = $s;
            }
            if (count($normalized) !== count(array_unique($normalized))) {
                throw new \InvalidArgumentException("Duplicate serial numbers in submission for item {$item->sku}.");
            }

            // Check none already in stock
            $conflicts = SerialNumber::where('item_id', $item->id)
                ->whereIn('serial_no', $normalized)
                ->where('status', SerialStatus::InStock)
                ->pluck('serial_no')
                ->all();
            if (! empty($conflicts)) {
                throw new \InvalidArgumentException('Serial(s) already in stock: ' . implode(', ', $conflicts));
            }

            $number = DocumentNumber::next('STK');
            $transaction = StockTransaction::create([
                'number'          => $number,
                'type'            => MovementType::SerialCapture,
                'reference_type'  => $ref->referenceType,
                'reference_id'    => $ref->referenceId,
                'reference_no'    => $ref->referenceNo,
                'idempotency_key' => $idempotencyKey,
                'notes'           => null,
                'posted_by'       => $user?->id,
                'posted_at'       => now(),
            ]);

            // Zero-qty movement record — audit trail only
            $movement = StockMovement::create([
                'stock_transaction_id' => $transaction->id,
                'item_id'              => $key->itemId,
                'batch_id'             => $key->batchId,
                'lpn_id'               => $key->lpnId,
                'location_id'          => $key->locationId,
                'virtual_warehouse_id' => $key->virtualWarehouseId,
                'stock_status'         => $key->stockStatus,
                'qty'                  => '0.0000',
                'entered_qty'          => '0.0000',
                'entered_uom_id'       => $item->base_uom_id,
            ]);

            $now = now();
            $insertRows = array_map(fn ($sn) => [
                'item_id'              => $item->id,
                'serial_no'            => $sn,
                'batch_id'             => $key->batchId,
                'location_id'          => $key->locationId,
                'virtual_warehouse_id' => $key->virtualWarehouseId,
                'lpn_id'               => $key->lpnId,
                'stock_status'         => $key->stockStatus->value,
                'status'               => SerialStatus::InStock->value,
                'last_transaction_id'  => $transaction->id,
                'created_at'           => $now,
                'updated_at'           => $now,
            ], $normalized);

            DB::table('serial_numbers')->upsert(
                $insertRows,
                ['item_id', 'serial_no'],
                ['batch_id', 'location_id', 'virtual_warehouse_id', 'lpn_id', 'stock_status', 'status', 'last_transaction_id', 'updated_at']
            );
            DB::table('serial_numbers')
                ->where('item_id', $item->id)
                ->whereIn('serial_no', $normalized)
                ->whereNull('first_received_at')
                ->update(['first_received_at' => $now]);

            $serialIds = SerialNumber::where('item_id', $item->id)
                ->whereIn('serial_no', $normalized)
                ->pluck('id')
                ->all();

            DB::table('stock_movement_serials')->insert(
                array_map(fn ($id) => [
                    'stock_movement_id' => $movement->id,
                    'serial_number_id'  => $id,
                ], $serialIds)
            );

            event(new StockPosted($transaction));

            return $transaction;
        });
    }

    public function reverse(StockTransaction $tx, string $reason): StockTransaction
    {
        if ($tx->reversed_by_transaction_id !== null) {
            throw new \DomainException('Transaction has already been reversed.');
        }

        // Reload movements (with their serials) to negate their qtys
        $movements = $tx->movements()->with('serials')->get();

        $reversalLines = $movements->map(function (StockMovement $m) {
            return new StockLine(
                itemId: $m->item_id,
                locationId: $m->location_id,
                virtualWarehouseId: $m->virtual_warehouse_id,
                stockStatus: $m->stock_status,
                enteredQty: bcmul($m->entered_qty, '-1', 4),
                enteredUomId: $m->entered_uom_id,
                batchId: $m->batch_id,
                lpnId: $m->lpn_id,
                serials: $m->serials->pluck('serial_no')->all(),
            );
        })->all();

        $reversal = $this->post(new StockPosting(
            MovementType::Reversal,
            new StockReference(
                referenceType: $tx->reference_type,
                referenceId: $tx->reference_id,
                referenceNo: $tx->reference_no,
            ),
            $reversalLines,
            null,
            $reason
        ));

        // Link the two transactions via direct table update (models are immutable)
        DB::table('stock_transactions')->where('id', $tx->id)->update([
            'reversed_by_transaction_id' => $reversal->id,
        ]);
        DB::table('stock_transactions')->where('id', $reversal->id)->update([
            'reverses_transaction_id' => $tx->id,
        ]);

        return $reversal;
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function processLine(StockTransaction $transaction, StockLine $line, array $releasedInThisPost = []): void
    {
        // 5a. Load and validate item existence (active check is delegated to shared validator for inbound)
        $item = Item::find($line->itemId);
        if ($item === null) {
            throw new \InvalidArgumentException("Item ID {$line->itemId} does not exist.");
        }
        if (! $item->is_active) {
            throw new \InvalidArgumentException("Item ID {$line->itemId} is not active or does not exist.");
        }

        // 5b. Batch resolution — track the pre-existing batch separately for expiry-conflict check
        $batch         = null;
        $existingBatch = null;
        if ($line->batchId !== null) {
            $batch = Batch::findOrFail($line->batchId);
        } elseif ($line->batchNo !== null) {
            $existing = Batch::where('item_id', $item->id)->where('batch_no', $line->batchNo)->first();
            if ($existing === null) {
                $batch = Batch::create([
                    'item_id'     => $item->id,
                    'batch_no'    => $line->batchNo,
                    'expiry_date' => $line->expiryDate,
                ]);
            } else {
                $existingBatch = $existing;
                $batch         = $existing;
            }
        }

        // 5c. Track-batch validation
        if ($item->track_batch && $batch === null) {
            throw new \InvalidArgumentException("Item {$item->sku} requires batch tracking but no batch was provided.");
        }

        // 5c2. Track-serial validation: non-serial items must not provide serials
        if (! $item->track_serial && ! empty($line->serials)) {
            throw new \InvalidArgumentException("Item {$item->sku} does not use serial tracking; remove serials from the line.");
        }

        // 5d. Track-expiry validation (only for newly created batch, expiry_date would be null if not set)
        if ($item->track_expiry && $batch !== null && $batch->expiry_date === null) {
            throw new \InvalidArgumentException("Item {$item->sku} requires expiry tracking but batch {$batch->batch_no} has no expiry date.");
        }

        // 5e. UoM conversion to base qty (signed decimal string)
        $baseQty = UomConverter::toBase($item, $line->enteredUomId, $line->enteredQty);

        if (bccomp($baseQty, '0', 4) === 0) {
            throw new \InvalidArgumentException('Stock line quantity cannot be zero.');
        }

        // 5e2. Serial count validation for serial-tracked items.
        // Inbound (qty > 0): serials are optional at dock receive — they can be captured during putaway.
        //   If provided, the count must match exactly.
        // Outbound (qty < 0): serials are always required and must match.
        if ($item->track_serial && ! empty($line->serials)) {
            $serialCount = count($line->serials);
            $absBaseQty  = (int) round((float) ltrim($baseQty, '-'));
            if ($serialCount !== $absBaseQty) {
                throw new \InvalidArgumentException(
                    "Item {$item->sku} requires {$absBaseQty} serial number(s) but {$serialCount} provided."
                );
            }
        }
        if ($item->track_serial && bccomp($baseQty, '0', 4) < 0 && empty($line->serials)) {
            $absBaseQty = (int) round((float) ltrim($baseQty, '-'));
            throw new \InvalidArgumentException(
                "Item {$item->sku} requires {$absBaseQty} serial number(s) for outbound movement but none were provided."
            );
        }

        // Resolve the effective stock status (may be overridden by quarantine location)
        $effectiveStatus = $line->stockStatus;

        // 5f. Location + VW check for inbound lines — delegates to shared validator
        if (bccomp($baseQty, '0', 4) > 0) {
            $location = Location::findOrFail($line->locationId);
            $vw       = VirtualWarehouse::findOrFail($line->virtualWarehouseId);

            StockLineValidator::assert(
                $item,
                $location,
                $vw,
                $existingBatch,
                $batch?->batch_no ?? $line->batchNo,
                $line->expiryDate?->toDateString() ?? $batch?->expiry_date?->toDateString(),
                $baseQty,
            );

            if ($location->type === LocationType::Quarantine) {
                $effectiveStatus = StockStatus::OnHold;
            }
        }

        // 5g. Build StockKey
        $key = new StockKey(
            itemId: $item->id,
            batchId: $batch?->id,
            lpnId: $line->lpnId,
            locationId: $line->locationId,
            virtualWarehouseId: $line->virtualWarehouseId,
            stockStatus: $effectiveStatus,
        );

        // 5g2. Serial pre-validation — before balance checks so errors are specific
        $normalizedSerials = [];
        if ($item->track_serial && ! empty($line->serials)) {
            $isInbound = bccomp($baseQty, '0', 4) > 0;

            foreach ($line->serials as $rawSerial) {
                $s = SerialNumber::normalize((string) $rawSerial);
                if ($s === '' || $s !== preg_replace('/\s+/', '', $s)) {
                    throw new \InvalidArgumentException("Serial number '{$rawSerial}' is invalid (empty or contains whitespace).");
                }
                $normalizedSerials[] = $s;
            }

            if (count($normalizedSerials) !== count(array_unique($normalizedSerials))) {
                throw new \InvalidArgumentException("Duplicate serial numbers in the same line for item {$item->sku}.");
            }

            if ($isInbound) {
                $toCheckForConflict = array_diff($normalizedSerials, $releasedInThisPost);
                if (! empty($toCheckForConflict)) {
                    $conflicts = SerialNumber::where('item_id', $item->id)
                        ->whereIn('serial_no', $toCheckForConflict)
                        ->where('status', SerialStatus::InStock)
                        ->pluck('serial_no')
                        ->all();
                    if (! empty($conflicts)) {
                        throw new \InvalidArgumentException('Serial(s) already in stock: ' . implode(', ', $conflicts));
                    }
                }
            } else {
                foreach ($normalizedSerials as $sn) {
                    $existing = SerialNumber::where('item_id', $item->id)
                        ->where('serial_no', $sn)
                        ->where('status', SerialStatus::InStock)
                        ->where('location_id', $key->locationId)
                        ->where('virtual_warehouse_id', $key->virtualWarehouseId)
                        ->where('stock_status', $key->stockStatus->value)
                        ->where(fn ($q) => $key->batchId === null
                            ? $q->whereNull('batch_id')
                            : $q->where('batch_id', $key->batchId))
                        ->where(fn ($q) => $key->lpnId === null
                            ? $q->whereNull('lpn_id')
                            : $q->where('lpn_id', $key->lpnId))
                        ->first();

                    if ($existing === null) {
                        throw new \InvalidArgumentException(
                            "Serial {$sn} is not available at the source stock key for item {$item->sku}."
                        );
                    }
                }
            }
        }

        // 5h. Find-or-create balance row with lock (handles race via unique constraint retry)
        $balance = StockBalance::where('key_hash', $key->hash())->lockForUpdate()->first();
        if ($balance === null) {
            try {
                $balance = StockBalance::create([
                    'item_id'              => $key->itemId,
                    'batch_id'             => $key->batchId,
                    'lpn_id'               => $key->lpnId,
                    'location_id'          => $key->locationId,
                    'virtual_warehouse_id' => $key->virtualWarehouseId,
                    'stock_status'         => $key->stockStatus,
                    'key_hash'             => $key->hash(),
                    'qty_on_hand'          => '0.0000',
                    'qty_allocated'        => '0.0000',
                ]);
            } catch (UniqueConstraintViolationException) {
                $balance = StockBalance::where('key_hash', $key->hash())->lockForUpdate()->firstOrFail();
            }
        }

        // 5i. Negative-stock check for outbound lines
        if (bccomp($baseQty, '0', 4) < 0) {
            $absQty = ltrim($baseQty, '-');
            if ($line->reservationId !== null) {
                if (bccomp($balance->qty_on_hand, $absQty, 4) < 0) {
                    throw new \DomainException('Insufficient available stock.');
                }
            } else {
                $available = bcsub((string) $balance->qty_on_hand, (string) $balance->qty_allocated, 4);
                if (bccomp($available, $absQty, 4) < 0) {
                    throw new \DomainException('Insufficient available stock.');
                }
            }
        }

        // 5j. Write StockMovement record
        $movement = StockMovement::create([
            'stock_transaction_id' => $transaction->id,
            'item_id'              => $key->itemId,
            'batch_id'             => $key->batchId,
            'lpn_id'               => $key->lpnId,
            'location_id'          => $key->locationId,
            'virtual_warehouse_id' => $key->virtualWarehouseId,
            'stock_status'         => $key->stockStatus,
            'qty'                  => $baseQty,
            'entered_qty'          => $line->enteredQty,
            'entered_uom_id'       => $line->enteredUomId,
        ]);

        // 5j2. Upsert serial positions and link to movement
        if ($item->track_serial && ! empty($normalizedSerials)) {
            $isInbound  = bccomp($baseQty, '0', 4) > 0;
            $now        = now();
            $newStatus  = $this->resolveSerialStatus($transaction->type, $isInbound);

            $insertRows = [];
            foreach ($normalizedSerials as $sn) {
                $insertRows[] = [
                    'item_id'              => $item->id,
                    'serial_no'            => $sn,
                    'batch_id'             => $key->batchId,
                    'location_id'          => $isInbound ? $key->locationId          : null,
                    'virtual_warehouse_id' => $isInbound ? $key->virtualWarehouseId  : null,
                    'lpn_id'               => $isInbound ? $key->lpnId               : null,
                    'stock_status'         => $isInbound ? $key->stockStatus->value  : null,
                    'status'               => $newStatus->value,
                    'first_received_at'    => null, // set below for new rows
                    'last_transaction_id'  => $transaction->id,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }

            // Bulk upsert — one row per physical serial; first_received_at only set on first insert
            DB::table('serial_numbers')->upsert(
                $insertRows,
                ['item_id', 'serial_no'],
                ['batch_id', 'location_id', 'virtual_warehouse_id', 'lpn_id', 'stock_status', 'status', 'last_transaction_id', 'updated_at']
            );

            // Set first_received_at on brand-new rows (upsert doesn't set it for new rows via the above)
            DB::table('serial_numbers')
                ->where('item_id', $item->id)
                ->whereIn('serial_no', $normalizedSerials)
                ->whereNull('first_received_at')
                ->update(['first_received_at' => $now]);

            // Link serials to the movement record
            $serialIds = SerialNumber::where('item_id', $item->id)
                ->whereIn('serial_no', $normalizedSerials)
                ->pluck('id')
                ->all();

            $linkRows = array_map(fn ($id) => [
                'stock_movement_id' => $movement->id,
                'serial_number_id'  => $id,
            ], $serialIds);

            DB::table('stock_movement_serials')->insert($linkRows);
        }

        // 5k. Update balance qty_on_hand; set received_at on first inbound posting
        $newQtyOnHand = bcadd((string) $balance->qty_on_hand, $baseQty, 4);
        DB::table('stock_balances')->where('id', $balance->id)->update([
            'qty_on_hand' => $newQtyOnHand,
            'updated_at'  => now(),
        ]);

        if (bccomp($baseQty, '0', 4) > 0) {
            DB::table('stock_balances')
                ->where('id', $balance->id)
                ->whereNull('received_at')
                ->update(['received_at' => now()]);
        }

        // 5l. Consume reservation if provided
        if ($line->reservationId !== null) {
            $reservation = StockReservation::findOrFail($line->reservationId);
            if ($reservation->status !== ReservationStatus::Active) {
                throw new \DomainException("Reservation #{$reservation->id} is {$reservation->status->value} and cannot be consumed.");
            }
            $reservation->status = ReservationStatus::Consumed;
            $reservation->save();

            $newQtyAllocated = bcsub((string) $balance->qty_allocated, (string) $reservation->qty, 4);
            DB::table('stock_balances')->where('id', $balance->id)->update([
                'qty_allocated' => $newQtyAllocated,
                'updated_at'    => now(),
            ]);
        }
    }

    // Determines the SerialStatus to assign based on movement direction and type.
    private function resolveSerialStatus(MovementType $type, bool $isInbound): SerialStatus
    {
        if ($isInbound) {
            return SerialStatus::InStock;
        }

        // Outbound
        return match($type) {
            MovementType::Dispatch => SerialStatus::Dispatched,
            MovementType::Adjustment, MovementType::CountAdjustment => SerialStatus::WrittenOff,
            default => SerialStatus::InStock, // move/hold/release — position update only, stays in_stock
        };
    }

    // Validates that lines net to zero per item+batch for paired movement types.
    private function assertNetZero(StockPosting $posting): void
    {
        $nets = [];
        foreach ($posting->lines as $line) {
            $key = "{$line->itemId}|" . ($line->batchId ?? 'null');
            $nets[$key] = bcadd($nets[$key] ?? '0.0000', $line->enteredQty, 4);
        }

        foreach ($nets as $key => $net) {
            if (bccomp($net, '0', 4) !== 0) {
                throw new \InvalidArgumentException(
                    "Lines do not net to zero for item/batch key [{$key}] in a {$posting->type->label()} posting. Net: {$net}"
                );
            }
        }
    }
}
