<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockReference;
use App\Enums\Inbound\ApprovalChainDocumentType;
use App\Enums\Inventory\DisposalStatus;
use App\Models\ApprovalChain;
use App\Models\ApprovalChainUser;
use App\Models\DisposalRequest;
use App\Models\DisposalRequestApproval;
use App\Models\DisposalRequestLine;
use App\Models\Inventory\StockBalance;
use App\Models\User;
use App\Services\Foundation\DocumentNumber;
use Illuminate\Support\Facades\DB;

final class DisposalWorkflow
{
    public function __construct(private readonly StockLedger $ledger) {}

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(array $data, User $user): DisposalRequest
    {
        return DB::transaction(function () use ($data, $user) {
            $req = DisposalRequest::create([
                'number'               => DocumentNumber::next('DISP'),
                'virtual_warehouse_id' => $data['virtual_warehouse_id'],
                'department_id'        => $data['department_id'] ?? $user->department_id,
                'method'               => $data['method'],
                'reason'               => $data['reason'],
                'status'               => DisposalStatus::Draft,
                'created_by'           => $user->id,
            ]);

            foreach ($data['lines'] as $lineData) {
                $line = DisposalRequestLine::create([
                    'disposal_request_id' => $req->id,
                    'item_id'             => $lineData['item_id'],
                    'location_id'         => $lineData['location_id'],
                    'batch_id'            => $lineData['batch_id'] ?? null,
                    'qty'                 => $lineData['qty'],
                    'reason'              => $lineData['reason'] ?? null,
                    'stock_status'        => $lineData['stock_status'] ?? null,
                ]);

                $line->value = $this->computeLineValue($line);
                $line->save();

                // Link serial pivots if provided
                if (! empty($lineData['serial_number_ids'])) {
                    foreach ($lineData['serial_number_ids'] as $snId) {
                        \App\Models\DisposalRequestLineSerial::create([
                            'disposal_request_line_id' => $line->id,
                            'serial_number_id'         => $snId,
                        ]);
                    }
                }
            }

            $req->total_value = $req->lines()->sum('value');
            $req->save();

            activity()->performedOn($req)->causedBy($user)->log('Disposal request created');

            return $req->fresh('lines');
        });
    }

    // ─── Submit ───────────────────────────────────────────────────────────────

    public function submit(DisposalRequest $req, User $user): DisposalRequest
    {
        if (! $req->status->canTransitionTo(DisposalStatus::Submitted)) {
            throw new \DomainException("Cannot submit a disposal request in status: {$req->status->label()}");
        }

        DB::transaction(function () use ($req, $user) {
            $this->checkAvailableQty($req);

            $req->status       = DisposalStatus::Submitted;
            $req->submitted_at = now();
            $req->save();
        });

        activity()->performedOn($req)->causedBy($user)->log('Disposal request submitted');

        return $req->fresh();
    }

    // ─── Approve ──────────────────────────────────────────────────────────────

    public function approve(DisposalRequest $req, User $user, ?string $comment = null): DisposalRequest
    {
        if (! $this->canApprove($req, $user)) {
            throw new \DomainException('You are not authorised to approve this disposal request at the current level.');
        }

        DB::transaction(function () use ($req, $user, $comment) {
            if ($req->status === DisposalStatus::Submitted) {
                // Level 1 — dept head
                DisposalRequestApproval::create([
                    'disposal_request_id' => $req->id,
                    'approval_chain_id'   => null,
                    'level'               => 1,
                    'user_id'             => $user->id,
                    'action'              => 'approved',
                    'comment'             => $comment,
                ]);
                $req->status = DisposalStatus::PendingWarehouseManagerApproval;
                $req->save();
                activity()->performedOn($req)->causedBy($user)->log('Approved at level 1 (dept head)');
            } else {
                // Level 2 — warehouse manager
                $this->checkAvailableQty($req);
                DisposalRequestApproval::create([
                    'disposal_request_id' => $req->id,
                    'approval_chain_id'   => null,
                    'level'               => 2,
                    'user_id'             => $user->id,
                    'action'              => 'approved',
                    'comment'             => $comment,
                ]);
                $req->status      = DisposalStatus::Approved;
                $req->approved_at = now();
                $req->approved_by = $user->id;
                $req->save();
                activity()->performedOn($req)->causedBy($user)->log('Approved at level 2 (warehouse manager)');
            }
        });

        return $req->fresh();
    }

    // ─── Reject ───────────────────────────────────────────────────────────────

    public function reject(DisposalRequest $req, User $user, string $reason): DisposalRequest
    {
        if (! in_array($req->status, [DisposalStatus::Submitted, DisposalStatus::Approved, DisposalStatus::Moved], true)) {
            throw new \DomainException("Cannot reject a disposal request in status: {$req->status->label()}");
        }

        DB::transaction(function () use ($req, $user, $reason) {
            DisposalRequestApproval::create([
                'disposal_request_id' => $req->id,
                'approval_chain_id'   => null,
                'level'               => 0,
                'user_id'             => $user->id,
                'action'              => 'rejected',
                'comment'             => $reason,
            ]);

            $req->status           = DisposalStatus::Rejected;
            $req->rejected_at      = now();
            $req->rejected_by      = $user->id;
            $req->rejection_reason = $reason;
            $req->save();
        });

        activity()->performedOn($req)->causedBy($user)->log('Disposal request rejected');

        return $req->fresh();
    }

    // ─── Move to disposal area ─────────────────────────────────────────────────

    public function moveToDisposalArea(DisposalRequest $req, int $disposalLocationId, User $user): DisposalRequest
    {
        if (! $req->status->canTransitionTo(DisposalStatus::Moved)) {
            throw new \DomainException("Cannot move disposal request in status: {$req->status->label()}");
        }

        DB::transaction(function () use ($req, $disposalLocationId, $user) {
            $req->loadMissing('lines.item');

            foreach ($req->lines as $line) {
                $stockStatus = $line->stock_status ?? \App\Enums\Inventory\StockStatus::Obsolete;
                $uomId = $line->item->base_uom_id;

                $from = new StockKey(
                    itemId:             $line->item_id,
                    batchId:            $line->batch_id,
                    lpnId:              null,
                    locationId:         $line->location_id,
                    virtualWarehouseId: $req->virtual_warehouse_id,
                    stockStatus:        $stockStatus,
                );

                $serials = $line->lineSerials()->with('serialNumber')->get()
                    ->pluck('serialNumber.serial_no')
                    ->filter()
                    ->all();

                $this->ledger->move(
                    from:           $from,
                    toLocationId:   $disposalLocationId,
                    qty:            (string) $line->qty,
                    uomId:          $uomId,
                    ref:            new StockReference(
                        referenceType: 'disposal_request',
                        referenceId:   $req->id,
                        referenceNo:   $req->number,
                    ),
                    serials:        $serials,
                    idempotencyKey: "disp:{$req->id}:move:line:{$line->id}",
                );

                // Update line to reflect new location
                $line->location_id = $disposalLocationId;
                $line->save();
            }

            $req->status  = DisposalStatus::Moved;
            $req->moved_at = now();
            $req->moved_by = $user->id;
            $req->save();
        });

        activity()->performedOn($req)->causedBy($user)->log('Disposal stock moved to disposal area');

        return $req->fresh();
    }

    // ─── Complete ─────────────────────────────────────────────────────────────

    public function complete(DisposalRequest $req, array $completionData, User $user): DisposalRequest
    {
        if (! $req->status->canTransitionTo(DisposalStatus::Completed)) {
            throw new \DomainException("Cannot complete a disposal request in status: {$req->status->label()}");
        }

        DB::transaction(function () use ($req, $completionData, $user) {
            $req->loadMissing('lines.item');

            foreach ($req->lines as $line) {
                $stockStatus = $line->stock_status ?? \App\Enums\Inventory\StockStatus::Obsolete;
                $uomId = $line->item->base_uom_id;

                $from = new StockKey(
                    itemId:             $line->item_id,
                    batchId:            $line->batch_id,
                    lpnId:              null,
                    locationId:         $line->location_id,
                    virtualWarehouseId: $req->virtual_warehouse_id,
                    stockStatus:        $stockStatus,
                );

                $serials = $line->lineSerials()->with('serialNumber')->get()
                    ->pluck('serialNumber.serial_no')
                    ->filter()
                    ->all();

                $this->ledger->dispose(
                    from:           $from,
                    qty:            (string) $line->qty,
                    uomId:          $uomId,
                    ref:            new StockReference(
                        referenceType: 'disposal_request',
                        referenceId:   $req->id,
                        referenceNo:   $req->number,
                    ),
                    serials:        $serials,
                    idempotencyKey: "disp:{$req->id}:line:{$line->id}",
                );
            }

            // Merge completion fields
            $updateFields = array_intersect_key($completionData, array_flip([
                'buyer_name', 'sale_price', 'sale_date', 'receipt_file_path',
                'destroy_method', 'destroy_date', 'witness_name', 'certificate_file_path',
            ]));

            $req->fill($updateFields);
            $req->status       = DisposalStatus::Completed;
            $req->completed_at = now();
            $req->completed_by = $user->id;
            $req->save();
        });

        activity()->performedOn($req)->causedBy($user)->log('Disposal request completed');

        return $req->fresh();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function canApprove(DisposalRequest $req, User $user): bool
    {
        if ($user->hasRole('system-admin')) {
            return true;
        }

        return match ($req->status) {
            DisposalStatus::Submitted =>
                $req->department?->head_user_id !== null
                && $req->department->head_user_id === $user->id,

            DisposalStatus::PendingWarehouseManagerApproval =>
                $user->hasRole('warehouse-manager'),

            default => false,
        };
    }

    private function checkAvailableQty(DisposalRequest $req): void
    {
        $req->loadMissing('lines');

        foreach ($req->lines as $line) {
            $stockStatus = $line->stock_status ?? \App\Enums\Inventory\StockStatus::Obsolete;

            $balance = StockBalance::where('item_id', $line->item_id)
                ->where('location_id', $line->location_id)
                ->where('virtual_warehouse_id', $req->virtual_warehouse_id)
                ->where('stock_status', $stockStatus)
                ->where(fn ($q) => $line->batch_id
                    ? $q->where('batch_id', $line->batch_id)
                    : $q->whereNull('batch_id'))
                ->lockForUpdate()
                ->first();

            $reserved = DisposalRequestLine::where('item_id', $line->item_id)
                ->where('location_id', $line->location_id)
                ->where(fn ($q) => $line->batch_id
                    ? $q->where('batch_id', $line->batch_id)
                    : $q->whereNull('batch_id'))
                ->whereHas('disposalRequest', fn ($q) => $q
                    ->where('virtual_warehouse_id', $req->virtual_warehouse_id)
                    ->whereIn('status', [
                        DisposalStatus::Submitted->value,
                        DisposalStatus::Approved->value,
                        DisposalStatus::Moved->value,
                    ])
                    ->where('id', '!=', $req->id))
                ->sum('qty');

            $available = bcsub((string) ($balance?->qty_on_hand ?? '0'), (string) $reserved, 4);

            if (bccomp((string) $line->qty, $available, 4) > 0) {
                throw new \DomainException("Insufficient available stock for disposal on line #{$line->id}.");
            }
        }
    }

    private function computeLineValue(DisposalRequestLine $line): ?string
    {
        if ($line->batch_id === null) {
            return null;
        }

        $unitPrice = DB::table('grn_lines')
            ->join('grn_receipts', 'grn_receipts.grn_line_id', '=', 'grn_lines.id')
            ->where('grn_receipts.batch_id', $line->batch_id)
            ->whereNotNull('grn_lines.unit_price')
            ->orderBy('grn_receipts.id')
            ->value('grn_lines.unit_price');

        if ($unitPrice === null) {
            return null;
        }

        return bcmul((string) $line->qty, (string) $unitPrice, 4);
    }

    private function getDepartmentChains(DisposalRequest $req): \Illuminate\Support\Collection
    {
        if ($req->department_id === null) {
            return collect();
        }

        return ApprovalChain::where('department_id', $req->department_id)
            ->where('document_type', ApprovalChainDocumentType::Disposal->value)
            ->with('chainUsers.user')
            ->orderBy('level')
            ->get();
    }

    private function currentDepartmentLevel(DisposalRequest $req, \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $chains): ?int
    {
        if ($chains->isEmpty()) {
            return null;
        }

        foreach ($chains as $chain) {
            $alreadyApproved = DisposalRequestApproval::where('disposal_request_id', $req->id)
                ->where('approval_chain_id', $chain->id)
                ->where('action', 'approved')
                ->exists();

            if (! $alreadyApproved) {
                return $chain->level;
            }
        }

        return null; // all dept levels approved
    }
}
