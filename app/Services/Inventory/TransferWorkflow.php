<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\SerialStatus;
use App\Enums\Inventory\StockStatus;
use App\Enums\TransferStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Inventory\SerialNumber;
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
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly TransferApprovalService $approvalService,
    ) {}

    public function create(array $data, User $user): StockTransfer
    {
        return DB::transaction(function () use ($data, $user) {
            $transfer = StockTransfer::create([
                'number'                    => DocumentNumber::next('STN'),
                'from_virtual_warehouse_id' => $data['from_virtual_warehouse_id'],
                'to_virtual_warehouse_id'   => $data['to_virtual_warehouse_id'],
                'is_physical'               => $data['is_physical'] ?? false,
                'created_by'                => $user->id,
                'department_id'             => $data['department_id'] ?? null,
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
                    'to_location_id'    => $line['to_location_id'] ?? null,
                ]);
            }

            activity()->performedOn($transfer)->causedBy($user)->log('Transfer created');

            return $transfer;
        });
    }

    public function submit(StockTransfer $transfer, User $user): StockTransfer
    {
        $resubmit = $transfer->status === TransferStatus::AwaitingRequesterCorrection;

        return DB::transaction(function () use ($transfer, $user, $resubmit) {
            $transfer->load('lines.item');

            if ($resubmit) {
                $this->approvalService->resetApprovals($transfer);
            } else {
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
            }

            $transfer->status = TransferStatus::Submitted;
            $transfer->save();

            $chains = $this->approvalService->getDepartmentChains($transfer);

            if ($chains->isEmpty()) {
                return $this->accept($transfer, $user);
            }

            foreach ($chains as $chain) {
                \App\Models\StockTransferApproval::create([
                    'stock_transfer_id' => $transfer->id,
                    'approval_chain_id' => $chain->id,
                    'level'             => $chain->level,
                    'approver_role'     => 'department',
                    'decision'          => null,
                ]);
            }

            activity()->performedOn($transfer)->causedBy($user)
                ->log($resubmit ? 'Resubmitted for approval' : 'Submitted for approval');

            return $transfer;
        });
    }

    /**
     * Accept a submitted transfer.
     *
     * For virtual (is_physical=false) transfers, acceptance immediately posts
     * the ownership change and transitions to Posted.
     *
     * For physical (is_physical=true) transfers, acceptance only sets the
     * Accepted status — posting is deferred until post() is called explicitly.
     */
    public function accept(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user) {
            $transfer->status      = $transfer->status->transitionTo(TransferStatus::Accepted);
            $transfer->accepted_by = $user->id;
            $transfer->accepted_at = now();
            $transfer->save();

            activity()->performedOn($transfer)->causedBy($user)->log('Transfer accepted');

            if (! $transfer->is_physical) {
                return $this->post($transfer, $user);
            }

            return $transfer;
        });
    }

    /**
     * Backward-compatible alias for accept().
     *
     * @deprecated Use accept() instead.
     */
    public function approve(StockTransfer $transfer, User $user): StockTransfer
    {
        return $this->accept($transfer, $user);
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

    public function approveDepartment(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user) {
            $chain = $this->approvalService->currentPendingChain($transfer);

            $this->approvalService->record(
                $transfer,
                $user,
                'approved',
                $chain?->level ?? 1,
                $chain?->id,
            );

            activity()->performedOn($transfer)->causedBy($user)->log('Department approval granted');

            if ($this->approvalService->isFullyApproved($transfer)) {
                // Bypass VW access check so restricted users (e.g. department heads)
                // can trigger ledger movements across VWs they are not assigned to.
                return \App\Support\VirtualWarehouseAccess::withoutChecks(
                    fn () => $this->accept($transfer, $user)
                );
            }

            return $transfer;
        });
    }

    public function returnForCorrection(StockTransfer $transfer, User $user, string $notes): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $notes) {
            if ($transfer->status !== TransferStatus::Submitted) {
                throw new \DomainException('Only submitted transfers can be returned for correction.');
            }

            $chain = $this->approvalService->currentPendingChain($transfer);

            $this->approvalService->record(
                $transfer,
                $user,
                'returned',
                $chain?->level ?? 1,
                $chain?->id,
                $notes,
            );

            $transfer->status = TransferStatus::ReturnedForCorrection;
            $transfer->save();

            activity()->performedOn($transfer)->causedBy($user)->log('Transfer returned for correction: ' . $notes);

            return $transfer;
        });
    }

    public function forwardToRequester(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user) {
            if ($transfer->status !== TransferStatus::ReturnedForCorrection) {
                throw new \DomainException('Only transfers returned for correction can be forwarded to the requester.');
            }

            $transfer->status = TransferStatus::AwaitingRequesterCorrection;
            $transfer->save();

            activity()->performedOn($transfer)->causedBy($user)->log('Transfer forwarded to requester for correction');

            return $transfer;
        });
    }

    /**
     * Post (execute) the stock ownership change for an accepted transfer.
     *
     * For virtual transfers this is called automatically by accept().
     * For physical transfers it must be called explicitly after physical
     * movement of goods has been confirmed.
     */
    public function post(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user) {
            if ($transfer->status === TransferStatus::Posted) {
                return $transfer;
            }

            // Accept both Accepted (new) and Approved (legacy) statuses
            $acceptedStatuses = [TransferStatus::Accepted, TransferStatus::Approved];

            if (! in_array($transfer->status, $acceptedStatuses, true)) {
                throw new InvalidTransitionException($transfer->status->value, TransferStatus::Posted->value);
            }

            $transfer->load('lines.item');

            // Validate physical transfer lines have destination locations
            if ($transfer->is_physical) {
                foreach ($transfer->lines as $line) {
                    if ($line->to_location_id === null) {
                        throw new \DomainException("Line {$line->id}: destination location required for physical transfer.");
                    }
                }
            }

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

                // For serial-tracked items, look up the serial numbers at the source key
                $serials = [];
                if ($line->item->track_serial) {
                    $serials = SerialNumber::where('item_id', $line->item_id)
                        ->where('location_id', $line->location_id)
                        ->where('virtual_warehouse_id', $transfer->from_virtual_warehouse_id)
                        ->where('status', SerialStatus::InStock)
                        ->when($line->batch_id, fn ($q) => $q->where('batch_id', $line->batch_id))
                        ->when($line->lpn_id, fn ($q) => $q->where('lpn_id', $line->lpn_id))
                        ->pluck('serial_no')
                        ->all();

                    if (empty($serials)) {
                        throw new \DomainException(
                            "Item {$line->item->sku} is serial-tracked but no serial numbers are registered at the source location. Attach serials to the stock before transferring."
                        );
                    }
                }

                // Change ownership from source VW to target VW (at the same location)
                $this->ledger->changeOwner(
                    $key,
                    $transfer->to_virtual_warehouse_id,
                    (string) $line->qty,
                    $line->uom_id,
                    $ref,
                    $serials,
                    "{$iKey}:line:{$line->id}:owner",
                );

                // For physical transfers: also relocate to the destination bin (now in target VW)
                if ($transfer->is_physical && $line->to_location_id !== null) {
                    $destKey = new StockKey(
                        itemId:             $line->item_id,
                        batchId:            $line->batch_id,
                        lpnId:              $line->lpn_id,
                        locationId:         $line->location_id,
                        virtualWarehouseId: $transfer->to_virtual_warehouse_id,
                        stockStatus:        StockStatus::Available,
                    );

                    $this->ledger->move(
                        $destKey,
                        $line->to_location_id,
                        (string) $line->qty,
                        $line->uom_id,
                        $ref,
                        $serials,
                        "{$iKey}:line:{$line->id}:move",
                    );
                }
            }

            $transfer->status          = TransferStatus::Posted;
            $transfer->posted_at       = now();
            $transfer->idempotency_key = $iKey;
            $transfer->save();

            activity()->performedOn($transfer)->causedBy($user)->log('Transfer posted');

            return $transfer;
        });
    }
}
