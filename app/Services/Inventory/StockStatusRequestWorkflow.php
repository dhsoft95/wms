<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockReference;
use App\Enums\Inbound\ApprovalChainDocumentType;
use App\Enums\Inventory\StockStatus;
use App\Enums\Inventory\StockStatusRequestStatus;
use App\Enums\Inventory\StockStatusRequestType;
use App\Models\ApprovalChain;
use App\Models\ApprovalChainUser;
use App\Models\Inventory\StockBalance;
use App\Models\StockStatusRequest;
use App\Models\StockStatusRequestApproval;
use App\Models\StockStatusRequestLine;
use App\Models\User;
use App\Services\Foundation\DocumentNumber;
use Illuminate\Support\Facades\DB;

final class StockStatusRequestWorkflow
{
    public function __construct(private readonly StockLedger $ledger) {}

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(array $data, User $user): StockStatusRequest
    {
        return DB::transaction(function () use ($data, $user) {
            $req = StockStatusRequest::create([
                'number'               => DocumentNumber::next('SSR'),
                'virtual_warehouse_id' => $data['virtual_warehouse_id'],
                'department_id'        => $data['department_id'] ?? $user->department_id,
                'type'                 => $data['type'],
                'reason'               => $data['reason'],
                'status'               => StockStatusRequestStatus::Draft,
                'created_by'           => $user->id,
            ]);

            foreach ($data['lines'] as $lineData) {
                $line = StockStatusRequestLine::create([
                    'stock_status_request_id' => $req->id,
                    'item_id'                 => $lineData['item_id'],
                    'location_id'             => $lineData['location_id'],
                    'batch_id'                => $lineData['batch_id'] ?? null,
                    'qty'                     => $lineData['qty'],
                ]);

                // Link serial pivots if provided
                if (! empty($lineData['serial_number_ids'])) {
                    foreach ($lineData['serial_number_ids'] as $snId) {
                        \App\Models\StockStatusRequestLineSerial::create([
                            'stock_status_request_line_id' => $line->id,
                            'serial_number_id'             => $snId,
                        ]);
                    }
                }
            }

            activity()->performedOn($req)->causedBy($user)->log('Stock status request created');

            return $req->fresh('lines');
        });
    }

    // ─── Submit ───────────────────────────────────────────────────────────────

    public function submit(StockStatusRequest $req, User $user): StockStatusRequest
    {
        if (! $req->status->canTransitionTo(StockStatusRequestStatus::Submitted)) {
            throw new \DomainException("Cannot submit a stock status request in status: {$req->status->label()}");
        }

        DB::transaction(function () use ($req, $user) {
            $this->checkAvailableQty($req);

            $req->status       = StockStatusRequestStatus::Submitted;
            $req->submitted_at = now();
            $req->save();
        });

        activity()->performedOn($req)->causedBy($user)->log('Stock status request submitted');

        return $req->fresh();
    }

    // ─── Approve ──────────────────────────────────────────────────────────────

    public function approve(StockStatusRequest $req, User $user, ?string $comment = null): StockStatusRequest
    {
        if (! $this->canApprove($req, $user)) {
            throw new \DomainException('You are not authorised to approve this stock status request at the current level.');
        }

        DB::transaction(function () use ($req, $user, $comment) {
            if ($req->status === StockStatusRequestStatus::Submitted) {
                // Level 1 — dept head
                StockStatusRequestApproval::create([
                    'stock_status_request_id' => $req->id,
                    'approval_chain_id'       => null,
                    'level'                   => 1,
                    'user_id'                 => $user->id,
                    'action'                  => 'approved',
                    'comment'                 => $comment,
                ]);
                $req->status = StockStatusRequestStatus::PendingWarehouseManagerApproval;
                $req->save();
                activity()->performedOn($req)->causedBy($user)->log('Approved at level 1 (dept head)');
            } else {
                // Level 2 — warehouse manager
                $this->checkAvailableQty($req);
                StockStatusRequestApproval::create([
                    'stock_status_request_id' => $req->id,
                    'approval_chain_id'       => null,
                    'level'                   => 2,
                    'user_id'                 => $user->id,
                    'action'                  => 'approved',
                    'comment'                 => $comment,
                ]);
                $this->applyStatusChanges($req, $user);
                $req->status      = StockStatusRequestStatus::Approved;
                $req->approved_at = now();
                $req->approved_by = $user->id;
                $req->save();
                activity()->performedOn($req)->causedBy($user)->log('Approved at level 2 (warehouse manager)');
            }
        });

        return $req->fresh();
    }

    // ─── Reject ───────────────────────────────────────────────────────────────

    public function reject(StockStatusRequest $req, User $user, string $reason): StockStatusRequest
    {
        if (! in_array($req->status, [
            StockStatusRequestStatus::Submitted,
            StockStatusRequestStatus::Approved,
        ], true)) {
            throw new \DomainException("Cannot reject a stock status request in status: {$req->status->label()}");
        }

        DB::transaction(function () use ($req, $user, $reason) {
            StockStatusRequestApproval::create([
                'stock_status_request_id' => $req->id,
                'approval_chain_id'       => null,
                'level'                   => 0,
                'user_id'                 => $user->id,
                'action'                  => 'rejected',
                'comment'                 => $reason,
            ]);

            $req->status           = StockStatusRequestStatus::Rejected;
            $req->rejected_at      = now();
            $req->rejected_by      = $user->id;
            $req->rejection_reason = $reason;
            $req->save();
        });

        activity()->performedOn($req)->causedBy($user)->log('Stock status request rejected');

        return $req->fresh();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function canApprove(StockStatusRequest $req, User $user): bool
    {
        if ($user->hasRole('system-admin')) {
            return true;
        }

        return match ($req->status) {
            StockStatusRequestStatus::Submitted =>
                $req->department?->head_user_id !== null
                && $req->department->head_user_id === $user->id,

            StockStatusRequestStatus::PendingWarehouseManagerApproval =>
                $user->hasRole('warehouse-manager'),

            default => false,
        };
    }

    private function checkAvailableQty(StockStatusRequest $req): void
    {
        $req->loadMissing('lines.item', 'lines.location');
        $fromStatus = $req->type->fromStatus();

        foreach ($req->lines as $line) {
            // When no batch specified, sum across all batches at that location
            $onHand = StockBalance::where('item_id', $line->item_id)
                ->where('location_id', $line->location_id)
                ->where('virtual_warehouse_id', $req->virtual_warehouse_id)
                ->where('stock_status', $fromStatus)
                ->when($line->batch_id, fn ($q) => $q->where('batch_id', $line->batch_id))
                ->lockForUpdate()
                ->sum('qty_on_hand');

            // Only count Submitted SSRs as reserved — Approved ones have already
            // been posted to the ledger so the balance already reflects them.
            $reserved = StockStatusRequestLine::where('item_id', $line->item_id)
                ->where('location_id', $line->location_id)
                ->when($line->batch_id, fn ($q) => $q->where('batch_id', $line->batch_id))
                ->whereHas('stockStatusRequest', fn ($q) => $q
                    ->where('virtual_warehouse_id', $req->virtual_warehouse_id)
                    ->where('type', $req->type)
                    ->where('status', StockStatusRequestStatus::Submitted->value)
                    ->where('id', '!=', $req->id))
                ->sum('qty');

            $available = bcsub((string) $onHand, (string) $reserved, 4);

            if (bccomp((string) $line->qty, $available, 4) > 0) {
                // Build a helpful hint showing which VW actually has stock here
                $hint = '';
                $elsewhere = StockBalance::where('item_id', $line->item_id)
                    ->where('location_id', $line->location_id)
                    ->where('stock_status', $fromStatus)
                    ->when($line->batch_id, fn ($q) => $q->where('batch_id', $line->batch_id))
                    ->where('qty_on_hand', '>', 0)
                    ->with('virtualWarehouse')
                    ->get();

                if ($elsewhere->isNotEmpty()) {
                    $vwNames = $elsewhere->map(fn ($b) => ($b->virtualWarehouse->name ?? "VW #{$b->virtual_warehouse_id}") . ' (' . $b->qty_on_hand . ')')->join(', ');
                    $hint = " Stock exists under: {$vwNames}. Make sure the Virtual Warehouse in the form header matches.";
                }

                $itemName = $line->item?->sku ?? "item #{$line->item_id}";
                $locCode  = $line->location?->code ?? "location #{$line->location_id}";

                throw new \DomainException(
                    "No {$fromStatus->label()} stock found for {$itemName} at {$locCode} under the selected virtual warehouse (available: {$available}).{$hint}"
                );
            }
        }
    }

    private function applyStatusChanges(StockStatusRequest $req, User $user): void
    {
        $req->loadMissing('lines.item');
        $fromStatus = $req->type->fromStatus();
        $toStatus   = $req->type->toStatus();

        $ref = new StockReference(
            referenceType: 'stock_status_request',
            referenceId:   $req->id,
            referenceNo:   $req->number,
        );

        foreach ($req->lines as $line) {
            $uomId    = $line->item->base_uom_id;
            $isSerial = (bool) $line->item->track_serial;

            $serials = $line->lineSerials()->with('serialNumber')->get()
                ->pluck('serialNumber.serial_no')
                ->filter()
                ->values()
                ->all();

            if ($isSerial && empty($serials)) {
                throw new \DomainException(
                    "Item {$line->item->sku} is serial-tracked. Please select serial numbers before approving."
                );
            }

            if ($line->batch_id) {
                $this->ledger->changeStatus(
                    from: new StockKey(
                        itemId:             $line->item_id,
                        batchId:            $line->batch_id,
                        lpnId:              null,
                        locationId:         $line->location_id,
                        virtualWarehouseId: $req->virtual_warehouse_id,
                        stockStatus:        $fromStatus,
                    ),
                    to:             $toStatus,
                    qty:            (string) $line->qty,
                    uomId:          $uomId,
                    ref:            $ref,
                    serials:        $serials,
                    idempotencyKey: "ssr:{$req->id}:line:{$line->id}",
                );
            } else {
                // No batch — drain across all batches, serials already verified above
                $balances  = StockBalance::where('item_id', $line->item_id)
                    ->where('location_id', $line->location_id)
                    ->where('virtual_warehouse_id', $req->virtual_warehouse_id)
                    ->where('stock_status', $fromStatus)
                    ->where('qty_on_hand', '>', 0)
                    ->get();

                $remaining       = (string) $line->qty;
                $serialsToAssign = $serials;

                foreach ($balances as $i => $balance) {
                    if (bccomp($remaining, '0', 4) <= 0) {
                        break;
                    }

                    $take = bccomp($remaining, (string) $balance->qty_on_hand, 4) >= 0
                        ? (string) $balance->qty_on_hand
                        : $remaining;

                    // Slice serials proportionally for this batch
                    $batchSerials    = $isSerial ? array_splice($serialsToAssign, 0, (int) round((float) $take)) : [];

                    $this->ledger->changeStatus(
                        from: new StockKey(
                            itemId:             $line->item_id,
                            batchId:            $balance->batch_id,
                            lpnId:              null,
                            locationId:         $line->location_id,
                            virtualWarehouseId: $req->virtual_warehouse_id,
                            stockStatus:        $fromStatus,
                        ),
                        to:             $toStatus,
                        qty:            $take,
                        uomId:          $uomId,
                        ref:            $ref,
                        serials:        $batchSerials,
                        idempotencyKey: "ssr:{$req->id}:line:{$line->id}:batch:{$i}",
                    );

                    $remaining = bcsub($remaining, $take, 4);
                }
            }
        }
    }

    private function getDepartmentChains(StockStatusRequest $req): \Illuminate\Support\Collection
    {
        if ($req->department_id === null) {
            return collect();
        }

        return ApprovalChain::where('department_id', $req->department_id)
            ->where('document_type', ApprovalChainDocumentType::StockStatus->value)
            ->with('chainUsers.user')
            ->orderBy('level')
            ->get();
    }

    private function currentDepartmentLevel(
        StockStatusRequest $req,
        \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $chains
    ): ?int {
        if ($chains->isEmpty()) {
            return null;
        }

        foreach ($chains as $chain) {
            $alreadyApproved = StockStatusRequestApproval::where('stock_status_request_id', $req->id)
                ->where('approval_chain_id', $chain->id)
                ->where('action', 'approved')
                ->exists();

            if (! $alreadyApproved) {
                return $chain->level;
            }
        }

        return null;
    }
}
