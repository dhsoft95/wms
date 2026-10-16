<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\StockStatus;
use App\Enums\TransferStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockReservation;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\User;
use App\Services\Foundation\DocumentNumber;
use App\Services\Foundation\UomConverter;
use Illuminate\Support\Facades\DB;

final class TransferWorkflow
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function create(array $data, User $user): StockTransfer
    {
        return DB::transaction(function () use ($data, $user) {
            $transfer = StockTransfer::create([
                'number'                    => DocumentNumber::next('TRF'),
                'from_virtual_warehouse_id' => $data['from_virtual_warehouse_id'],
                'to_virtual_warehouse_id'   => $data['to_virtual_warehouse_id'],
                'created_by'                => $user->id,
                'status'                    => TransferStatus::Draft,
                'notes'                     => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                StockTransferLine::create([
                    'stock_transfer_id' => $transfer->id,
                    'item_id'           => $line['item_id'],
                    'location_id'       => $line['location_id'],
                    'batch_id'          => $line['batch_id'] ?? null,
                    'lpn_id'            => $line['lpn_id'] ?? null,
                    'qty'               => $line['qty'],
                    'uom_id'            => $line['uom_id'],
                ]);
            }

            activity()->performedOn($transfer)->causedBy($user)->log('Transfer created');

            return $transfer;
        });
    }

    public function submit(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user) {
            $transfer->load('lines.item');

            // Reserved stock cannot be transferred — check available balance per line
            foreach ($transfer->lines as $line) {
                $baseQty = UomConverter::toBase($line->item, $line->uom_id, (string) $line->qty);

                $balance = StockBalance::where('item_id', $line->item_id)
                    ->where('location_id', $line->location_id)
                    ->where('virtual_warehouse_id', $transfer->from_virtual_warehouse_id)
                    ->where('stock_status', StockStatus::Available->value)
                    ->when($line->batch_id, fn ($q) => $q->where('batch_id', $line->batch_id))
                    ->first();

                $available = $balance
                    ? bcsub((string) $balance->qty_on_hand, (string) $balance->qty_allocated, 4)
                    : '0.0000';

                if (bccomp($available, $baseQty, 4) < 0) {
                    throw new \DomainException(
                        "Item {$line->item->sku} has insufficient available stock for transfer (reserved stock cannot be transferred)."
                    );
                }
            }

            $transfer->status = $transfer->status->transitionTo(TransferStatus::Submitted);
            $transfer->save();

            activity()->performedOn($transfer)->causedBy($user)->log('Transfer submitted for approval');

            return $transfer;
        });
    }

    public function approve(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user) {
            $transfer->status      = $transfer->status->transitionTo(TransferStatus::Approved);
            $transfer->approved_by = $user->id;
            $transfer->save();

            activity()->performedOn($transfer)->causedBy($user)->log('Transfer approved');

            return $this->post($transfer, $user);
        });
    }

    public function reject(StockTransfer $transfer, User $user, string $reason): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $reason) {
            $transfer->status           = $transfer->status->transitionTo(TransferStatus::Rejected);
            $transfer->rejection_reason = $reason;
            $transfer->save();

            activity()->performedOn($transfer)->causedBy($user)->log('Transfer rejected: ' . $reason);

            return $transfer;
        });
    }

    public function post(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user) {
            if ($transfer->status === TransferStatus::Posted) {
                return $transfer;
            }

            if ($transfer->status !== TransferStatus::Approved) {
                throw new InvalidTransitionException($transfer->status->value, TransferStatus::Posted->value);
            }

            $transfer->load('lines.item');

            $iKey = "trf:{$transfer->id}:post";

            $ref = new StockReference(
                referenceType: 'stock_transfer',
                referenceId:   $transfer->id,
                referenceNo:   $transfer->number,
            );

            foreach ($transfer->lines as $line) {
                $key = new StockKey(
                    itemId:             $line->item_id,
                    batchId:            $line->batch_id,
                    lpnId:              $line->lpn_id,
                    locationId:         $line->location_id,
                    virtualWarehouseId: $transfer->from_virtual_warehouse_id,
                    stockStatus:        StockStatus::Available,
                );

                $this->ledger->changeOwner(
                    $key,
                    $transfer->to_virtual_warehouse_id,
                    (string) $line->qty,
                    $line->uom_id,
                    $ref,
                    [],
                    "{$iKey}:line:{$line->id}",
                );
            }

            $transfer->status        = TransferStatus::Posted;
            $transfer->posted_at     = now();
            $transfer->idempotency_key = $iKey;
            $transfer->save();

            activity()->performedOn($transfer)->causedBy($user)->log('Transfer posted');

            return $transfer;
        });
    }
}
