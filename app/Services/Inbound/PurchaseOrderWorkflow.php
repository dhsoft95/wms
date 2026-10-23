<?php

declare(strict_types=1);

namespace App\Services\Inbound;

use App\Enums\Inbound\ApprovalChainDocumentType;
use App\Enums\Inbound\PackageType;
use App\Enums\Inbound\PoIssueStatus;
use App\Enums\Inbound\PurchaseOrderApprovalAction;
use App\Enums\Inbound\PurchaseOrderSource;
use App\Enums\Inbound\PurchaseOrderStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\ApprovalChain;
use App\Models\ItemCategoryUser;
use App\Models\PoIssue;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAssignment;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Notifications\Inbound\PoApprovalRequestedNotification;
use App\Notifications\Inbound\PoStatusChangedNotification;
use App\Services\Foundation\DocumentNumber;
use Illuminate\Support\Facades\DB;

final class PurchaseOrderWorkflow
{
    public function __construct(
        private readonly ApprovalChainService $chainService,
    ) {}

    // ─── Creation ─────────────────────────────────────────────────────────────

    public function create(array $data, User $by, bool $systemCreated = false): PurchaseOrder
    {
        if (! $systemCreated && ! $by->hasAnyRole(['department-user', 'warehouse-manager', 'system-admin'])) {
            throw new \DomainException('Only department users may create purchase orders.');
        }

        return DB::transaction(function () use ($data, $by) {
            $po = PurchaseOrder::create([
                'number'               => DocumentNumber::next('PO'),
                'status'               => PurchaseOrderStatus::Draft,
                'requested_by'         => $by->id,
                'warehouse_id'         => $data['warehouse_id'] ?? null,
                'virtual_warehouse_id' => $data['virtual_warehouse_id'] ?? null,
                'department_id'        => $data['department_id'] ?? null,
                'supplier_partner_id'  => $data['supplier_partner_id'] ?? null,
                'consignee_partner_id' => $data['consignee_partner_id'] ?? null,
                'currency'             => $data['currency'] ?? 'TZS',
                'package_type'         => $data['package_type'] ?? PackageType::LooseCargo,
                'source'               => $data['source'] ?? PurchaseOrderSource::Manual,
                'expected_date'        => $data['expected_date'] ?? null,
                'customer_po_ref'      => $data['customer_po_ref'] ?? null,
                'po_file_path'         => $data['po_file_path'] ?? null,
                'container_id'             => $data['container_id'] ?? null,
                'original_do_id'           => $data['original_do_id'] ?? null,
                'notes'                    => $data['notes'] ?? null,
                'is_special_treatment'     => $data['is_special_treatment'] ?? false,
                'special_treatment_reason' => $data['special_treatment_reason'] ?? null,
            ]);

            foreach ($data['lines'] ?? [] as $index => $line) {
                $qty    = (string) $line['qty'];
                $price  = (string) ($line['unit_price'] ?? '0');
                $total  = bcmul($qty, $price, 4);

                PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'line_no'           => $index + 1,
                    'item_id'           => $line['item_id'],
                    'uom_id'            => $line['uom_id'],
                    'part_number'       => $line['part_number'] ?? null,
                    'qty'               => $qty,
                    'qty_base'          => $line['qty_base'],
                    'currency'          => $line['currency'] ?? null,
                    'unit_price'        => $price,
                    'line_total'        => $total,
                    'batch_no'          => $line['batch_no'] ?? null,
                    'notes'             => $line['notes'] ?? null,
                ]);
            }

            activity()->performedOn($po)->causedBy($by)->log('Purchase order created');

            return $po;
        });
    }

    public function updateLine(PurchaseOrderLine $line, array $data): void
    {
        $qty   = (string) ($data['qty'] ?? $line->qty);
        $price = (string) ($data['unit_price'] ?? $line->unit_price);
        $line->update([
            'uom_id'     => $data['uom_id'] ?? $line->uom_id,
            'qty'        => $qty,
            'qty_base'   => $data['qty_base'] ?? $line->qty_base,
            'unit_price' => $price,
            'line_total' => bcmul($qty, $price, 4),
            'batch_no'   => $data['batch_no'] ?? $line->batch_no,
            'notes'      => $data['notes'] ?? $line->notes,
        ]);
    }

    // ─── Submission & approval chain ──────────────────────────────────────────

    public function submit(PurchaseOrder $po, User $by): void
    {
        $this->assertStatus($po, PurchaseOrderStatus::Draft, PurchaseOrderStatus::ReturnedForCorrection);

        if ($by->id !== $po->requested_by && ! $by->hasAnyRole(['system-admin', 'warehouse-manager'])) {
            throw new \DomainException('Only the creator may submit this purchase order.');
        }

        DB::transaction(function () use ($po, $by) {
            // Restart approvals when resubmitting after return
            if ($po->status === PurchaseOrderStatus::ReturnedForCorrection) {
                $this->chainService->resetApprovals($po);
            }

            $po->submitted_at = now();
            $po->status       = PurchaseOrderStatus::PendingApproval;
            $po->save();

            activity()->performedOn($po)->causedBy($by)->log('Submitted for approval');

            $this->notifyNextApprovers($po);
        });
    }

    public function approveAtLevel(
        PurchaseOrder $po,
        User $by,
        ?string $comment = null,
    ): void {
        $this->assertStatus($po, PurchaseOrderStatus::PendingApproval);

        DB::transaction(function () use ($po, $by, $comment) {
            // Admin override: approve all remaining dept levels + all pending categories at once
            if ($by->hasRole('system-admin')) {
                foreach ($this->chainService->getDepartmentChains($po) as $chain) {
                    $alreadyDone = \App\Models\PurchaseOrderApproval::where('purchase_order_id', $po->id)
                        ->where('approval_chain_id', $chain->id)
                        ->where('action', PurchaseOrderApprovalAction::Approved->value)
                        ->exists();
                    if (! $alreadyDone) {
                        $this->chainService->record(
                            $po, $by, PurchaseOrderApprovalAction::Approved,
                            $chain->level, $chain->id, null, $comment,
                        );
                    }
                }

                foreach ($this->chainService->pendingCategoryApprovals($po) as $categoryId) {
                    $this->chainService->record(
                        $po, $by, PurchaseOrderApprovalAction::Approved,
                        99, null, $categoryId, $comment,
                    );
                }

                // Fallback: no chain and no categories configured
                if ($this->chainService->getDepartmentChains($po)->isEmpty()
                    && $this->chainService->pendingCategoryApprovals($po)->isEmpty()
                    && ! $this->chainService->isFullyApproved($po)
                ) {
                    $this->chainService->record(
                        $po, $by, PurchaseOrderApprovalAction::Approved,
                        0, null, null, $comment,
                    );
                }

                activity()->performedOn($po)->causedBy($by)->log('Admin override: approved entire chain');
            } else {
            // Determine whether this is a department-level or category-level action
            $deptLevel = $this->chainService->currentDepartmentLevel($po);
            $pendingCategories = $this->chainService->pendingCategoryApprovals($po);

            if ($deptLevel !== null) {
                // Regular approver: approve only the current level
                $chain = ApprovalChain::where('department_id', $po->department_id)
                    ->where('document_type', ApprovalChainDocumentType::Po->value)
                    ->where('level', $deptLevel)
                    ->firstOrFail();

                $this->chainService->record(
                    $po, $by, PurchaseOrderApprovalAction::Approved,
                    $deptLevel, $chain->id, null, $comment,
                );

                activity()->performedOn($po)->causedBy($by)
                    ->log("Approved at department level {$deptLevel}");
            } elseif ($pendingCategories->isNotEmpty()) {
                // Category-level approval
                $userCategoryIds = ItemCategoryUser::where('user_id', $by->id)
                    ->where('warehouse_id', $po->warehouse_id)
                    ->whereIn('item_category_id', $pendingCategories)
                    ->pluck('item_category_id');

                if ($userCategoryIds->isEmpty()) {
                    throw new \DomainException('You have no pending category approvals on this purchase order.');
                }

                foreach ($userCategoryIds as $categoryId) {
                    $this->chainService->record(
                        $po, $by, PurchaseOrderApprovalAction::Approved,
                        99, null, $categoryId, $comment,
                    );
                }

                activity()->performedOn($po)->causedBy($by)
                    ->log('Category-level approval recorded');
            } else {
                // No chain configured — warehouse manager acts as fallback approver
                if (! $by->hasAnyRole(['warehouse-manager', 'system-admin'])) {
                    throw new \DomainException('No approval chain is configured. Only a warehouse manager can approve this order.');
                }

                $this->chainService->record(
                    $po, $by, PurchaseOrderApprovalAction::Approved,
                    0, null, null, $comment,
                );

                activity()->performedOn($po)->causedBy($by)
                    ->log('Approved by warehouse manager (no chain configured)');
            }
            } // end non-admin branch

            // Advance if fully approved
            if ($this->chainService->isFullyApproved($po)) {
                $po->status      = PurchaseOrderStatus::ApprovedPendingAcceptance;
                $po->approved_at = now();
                $po->save();

                activity()->performedOn($po)->causedBy($by)->log('Fully approved');

                // Notify warehouse managers
                $managers = User::role('warehouse-manager')->get();
                foreach ($managers as $manager) {
                    $manager->notify(new PoStatusChangedNotification(
                        $po, 'approved',
                        "PO {$po->number} has been approved and is awaiting warehouse acceptance.",
                    ));
                }
            } else {
                // Notify next approvers
                $this->notifyNextApprovers($po);
            }
        });
    }

    /**
     * Send the PO back one step in the approval chain.
     * - If there is a previously-approved dept level: revoke it and re-notify those approvers.
     * - If there are only category approvals: revoke them and return to the last dept level.
     * - If nothing has been approved yet (level 1 or no chain): return to the requester.
     */
    public function returnToPreviousLevel(PurchaseOrder $po, User $by, string $comment): void
    {
        $this->assertStatus($po, PurchaseOrderStatus::PendingApproval);

        DB::transaction(function () use ($po, $by, $comment) {
            $chains = $this->chainService->getDepartmentChains($po);

            // Find the last dept level that has an approved record
            $lastApprovedChain = null;
            foreach ($chains->sortByDesc('level') as $chain) {
                $exists = \App\Models\PurchaseOrderApproval::where('purchase_order_id', $po->id)
                    ->where('approval_chain_id', $chain->id)
                    ->where('action', PurchaseOrderApprovalAction::Approved->value)
                    ->exists();
                if ($exists) {
                    $lastApprovedChain = $chain;
                    break;
                }
            }

            // If only category approvals exist (no approved dept level found but categories approved)
            $hasCategoryApprovals = \App\Models\PurchaseOrderApproval::where('purchase_order_id', $po->id)
                ->whereNull('approval_chain_id')
                ->whereNotNull('item_category_id')
                ->where('action', PurchaseOrderApprovalAction::Approved->value)
                ->exists();

            if ($lastApprovedChain === null && ! $hasCategoryApprovals) {
                // Nothing approved yet → return all the way to the requester
                $this->returnForCorrection($po, $by, $comment);
                return;
            }

            // Record the send-back in history
            $currentLevel = $this->chainService->currentDepartmentLevel($po) ?? 99;
            $this->chainService->record(
                $po, $by, PurchaseOrderApprovalAction::Returned,
                $currentLevel, null, null, $comment,
            );

            // Wipe category approvals (always reset these when stepping back)
            \App\Models\PurchaseOrderApproval::where('purchase_order_id', $po->id)
                ->whereNull('approval_chain_id')
                ->whereNotNull('item_category_id')
                ->where('action', PurchaseOrderApprovalAction::Approved->value)
                ->delete();

            if ($lastApprovedChain !== null) {
                // Revoke the last approved dept level
                \App\Models\PurchaseOrderApproval::where('purchase_order_id', $po->id)
                    ->where('approval_chain_id', $lastApprovedChain->id)
                    ->where('action', PurchaseOrderApprovalAction::Approved->value)
                    ->delete();

                activity()->performedOn($po)->causedBy($by)
                    ->withProperties(['comment' => $comment])
                    ->log("Sent back to level {$lastApprovedChain->level}");

                // Notify the approvers at that level
                $users = $this->chainService->usersForDepartmentLevel($po, $lastApprovedChain->level);
                foreach ($users as $user) {
                    $user->notify(new PoStatusChangedNotification(
                        $po, 'returned',
                        "PO {$po->number} has been sent back to you for review. Remark: {$comment}",
                        $comment,
                    ));
                }
            } else {
                // Had only category approvals, no dept level → notify current dept level approvers
                $this->notifyNextApprovers($po);
            }

            // PO stays in PendingApproval — no status change needed
        });
    }

    public function returnForCorrection(PurchaseOrder $po, User $by, string $comment): void
    {
        $this->assertStatus($po, PurchaseOrderStatus::PendingApproval);

        DB::transaction(function () use ($po, $by, $comment) {
            $deptLevel = $this->chainService->currentDepartmentLevel($po) ?? 99;
            $chainId   = null;
            if ($deptLevel !== 99 && $po->department_id) {
                $chain   = ApprovalChain::where('department_id', $po->department_id)
                    ->where('document_type', ApprovalChainDocumentType::Po->value)
                    ->where('level', $deptLevel)
                    ->first();
                $chainId = $chain?->id;
            }

            $this->chainService->record(
                $po, $by, PurchaseOrderApprovalAction::Returned,
                $deptLevel, $chainId, null, $comment,
            );

            $po->status = PurchaseOrderStatus::ReturnedForCorrection;
            $po->save();

            activity()->performedOn($po)->causedBy($by)
                ->withProperties(['comment' => $comment])
                ->log('Returned for correction');

            // Notify the creator
            $creator = $po->requestedBy;
            $creator?->notify(new PoStatusChangedNotification(
                $po, 'returned',
                "PO {$po->number} has been returned for correction.",
                $comment,
            ));
        });
    }

    public function reject(PurchaseOrder $po, User $by, string $reason): void
    {
        $this->assertStatus($po, PurchaseOrderStatus::PendingApproval);

        DB::transaction(function () use ($po, $by, $reason) {
            $deptLevel = $this->chainService->currentDepartmentLevel($po) ?? 99;
            $chainId   = null;
            if ($deptLevel !== 99 && $po->department_id) {
                $chain   = ApprovalChain::where('department_id', $po->department_id)
                    ->where('document_type', ApprovalChainDocumentType::Po->value)
                    ->where('level', $deptLevel)
                    ->first();
                $chainId = $chain?->id;
            }

            $this->chainService->record(
                $po, $by, PurchaseOrderApprovalAction::Rejected,
                $deptLevel, $chainId, null, $reason,
            );

            $po->status           = PurchaseOrderStatus::Rejected;
            $po->rejection_reason = $reason;
            $po->rejected_at      = now();
            $po->save();

            activity()->performedOn($po)->causedBy($by)
                ->withProperties(['reason' => $reason])
                ->log('Purchase order rejected');

            $po->requestedBy?->notify(new PoStatusChangedNotification(
                $po, 'rejected',
                "PO {$po->number} has been rejected.",
                $reason,
            ));
        });
    }

    // ─── Warehouse acceptance & assignment ────────────────────────────────────

    public function accept(PurchaseOrder $po, User $by): void
    {
        $this->assertStatus($po, PurchaseOrderStatus::ApprovedPendingAcceptance);
        $this->assertRole($by, 'warehouse-manager', 'system-admin');

        DB::transaction(function () use ($po, $by) {
            $po->status      = PurchaseOrderStatus::AcceptedPendingAssignment;
            $po->accepted_at = now();
            $po->save();

            activity()->performedOn($po)->causedBy($by)->log('Accepted by warehouse manager');

            $po->requestedBy?->notify(new PoStatusChangedNotification(
                $po, 'accepted',
                "PO {$po->number} has been accepted by the warehouse.",
            ));
        });
    }

    public function rejectByWarehouse(PurchaseOrder $po, User $by, string $reason): void
    {
        $this->assertStatus($po, PurchaseOrderStatus::ApprovedPendingAcceptance);
        $this->assertRole($by, 'warehouse-manager', 'system-admin');

        DB::transaction(function () use ($po, $by, $reason) {
            $po->status                      = PurchaseOrderStatus::Rejected;
            $po->acceptance_rejection_reason = $reason;
            $po->rejected_at                 = now();
            $po->save();

            activity()->performedOn($po)->causedBy($by)
                ->withProperties(['reason' => $reason])
                ->log('Rejected by warehouse manager');

            $po->requestedBy?->notify(new PoStatusChangedNotification(
                $po, 'warehouse_rejected',
                "PO {$po->number} has been rejected by the warehouse.",
                $reason,
            ));
        });
    }

    public function assignSupervisor(PurchaseOrder $po, User $supervisor, User $by): void
    {
        $this->assertStatus($po, PurchaseOrderStatus::AcceptedPendingAssignment);
        $this->assertRole($by, 'warehouse-manager', 'system-admin');

        if (! $supervisor->hasAnyRole(['warehouse-supervisor', 'system-admin'])) {
            throw new \DomainException('The assigned user must have the warehouse-supervisor role.');
        }

        DB::transaction(function () use ($po, $supervisor, $by) {
            $po->assigned_supervisor_id = $supervisor->id;
            $po->assigned_supervisor_at = now();
            $po->status                 = PurchaseOrderStatus::AssignedPendingReceiving;
            $po->save();

            PurchaseOrderAssignment::create([
                'purchase_order_id' => $po->id,
                'type'              => 'supervisor',
                'assigned_user_id'  => $supervisor->id,
                'assigned_by'       => $by->id,
            ]);

            activity()->performedOn($po)->causedBy($by)
                ->log("Supervisor assigned: {$supervisor->name}");

            $supervisor->notify(new PoStatusChangedNotification(
                $po, 'supervisor_assigned',
                "PO {$po->number} has been assigned to you as supervisor.",
            ));
        });
    }

    public function assignClerk(PurchaseOrder $po, User $clerk, User $by): void
    {
        $this->assertStatus($po, PurchaseOrderStatus::AssignedPendingReceiving);
        $this->assertRole($by, 'warehouse-manager', 'warehouse-supervisor', 'system-admin');

        if (! $clerk->hasAnyRole(['storekeeper', 'system-admin'])) {
            throw new \DomainException('The assigned user must have the storekeeper role.');
        }

        DB::transaction(function () use ($po, $clerk, $by) {
            $previous = $po->assigned_clerk_id;

            $po->assigned_clerk_id  = $clerk->id;
            $po->assigned_clerk_at  = now();
            $po->save();

            PurchaseOrderAssignment::create([
                'purchase_order_id' => $po->id,
                'type'              => 'clerk',
                'assigned_user_id'  => $clerk->id,
                'assigned_by'       => $by->id,
                'previous_user_id'  => $previous,
            ]);

            activity()->performedOn($po)->causedBy($by)
                ->withProperties(['previous_clerk_id' => $previous])
                ->log("Clerk assigned: {$clerk->name}");

            $clerk->notify(new PoStatusChangedNotification(
                $po, 'clerk_assigned',
                "PO {$po->number} has been assigned to you for receiving.",
            ));
        });
    }

    // ─── Putaway & validation ─────────────────────────────────────────────────

    public function markReceivingInProgress(PurchaseOrder $po, User $by): void
    {
        // Already in progress (e.g. second delivery on a partial order) — no-op
        if ($po->status === PurchaseOrderStatus::ReceivingInProgress) {
            return;
        }

        $this->assertStatus($po, PurchaseOrderStatus::AssignedPendingReceiving, PurchaseOrderStatus::PartiallyReceived);

        $po->status = PurchaseOrderStatus::ReceivingInProgress;
        $po->save();

        activity()->performedOn($po)->causedBy($by)->log('Receiving started');
    }

    /**
     * Called by GrnWorkflow after each GRN is confirmed.
     * Advances PO status based on whether all lines are now fully received.
     */
    public function onGrnConfirmed(PurchaseOrder $po, User $by): void
    {
        if (! in_array($po->status, [
            PurchaseOrderStatus::ReceivingInProgress,
            PurchaseOrderStatus::PartiallyReceived,
        ], true)) {
            return;
        }

        $allReceived = $this->areAllLinesReceived($po);

        if ($allReceived) {
            $po->status      = PurchaseOrderStatus::PendingPutaway;
            $po->received_at = now();
        } else {
            $po->status = PurchaseOrderStatus::PartiallyReceived;
        }

        $po->save();

        if (! $allReceived) {
            $po->requestedBy?->notify(new PoStatusChangedNotification(
                $po, 'partial_receipt',
                "PO {$po->number}: partial delivery recorded. Awaiting further deliveries.",
            ));
        }
    }

    public function markPutawayStarted(PurchaseOrder $po, User $by): void
    {
        $this->assertStatus($po, PurchaseOrderStatus::PendingPutaway);

        $po->status = PurchaseOrderStatus::PutawayInProgress;
        $po->save();

        activity()->performedOn($po)->causedBy($by)->log('Putaway started');
    }

    public function markPutawayComplete(PurchaseOrder $po, User $by): void
    {
        $this->assertStatus($po, PurchaseOrderStatus::PutawayInProgress);

        $po->status = PurchaseOrderStatus::PendingSupervisorValidation;
        $po->save();

        activity()->performedOn($po)->causedBy($by)->log('Putaway complete  awaiting supervisor validation');

        $supervisor = $po->assignedSupervisor;
        $supervisor?->notify(new PoStatusChangedNotification(
            $po, 'pending_validation',
            "PO {$po->number} putaway is complete. Please validate.",
        ));
    }

    public function validateBySupevisor(PurchaseOrder $po, User $by): void
    {
        $this->assertStatus($po, PurchaseOrderStatus::PendingSupervisorValidation);
        $this->assertCanValidate($po, $by);

        DB::transaction(function () use ($po, $by) {
            $po->status              = PurchaseOrderStatus::CompletedPendingVerification;
            $po->putaway_validated_at = now();
            $po->save();

            activity()->performedOn($po)->causedBy($by)->log('Supervisor validation complete');

            // Notify all approvers who participated
            $this->notifyApproversForVerification($po);
        });
    }

    public function sendBackFromValidation(PurchaseOrder $po, User $by, string $comment): void
    {
        $this->assertStatus($po, PurchaseOrderStatus::PendingSupervisorValidation);
        $this->assertCanValidate($po, $by);

        $po->status = PurchaseOrderStatus::PutawayInProgress;
        $po->save();

        activity()->performedOn($po)->causedBy($by)
            ->withProperties(['comment' => $comment])
            ->log('Sent back to clerk for correction');

        $po->assignedClerk?->notify(new PoStatusChangedNotification(
            $po, 'validation_rejected',
            "PO {$po->number} putaway was sent back for correction.",
            $comment,
        ));
    }

    // ─── Excess & seal mismatch exceptions ───────────────────────────────────

    public function acknowledgeSealmismatch(PurchaseOrder $po, \App\Models\Grn $grn, User $by): void
    {
        $this->assertRole($by, 'warehouse-manager', 'system-admin');

        $grn->seal_mismatch_acknowledged_by = $by->id;
        $grn->seal_mismatch_acknowledged_at = now();
        $grn->save();

        activity()->performedOn($grn)->causedBy($by)
            ->log("Seal mismatch acknowledged for GRN {$grn->number}");
    }

    public function authoriseExcess(PurchaseOrder $po, \App\Models\GrnLine $line, User $by): void
    {
        $this->assertRole($by, 'warehouse-manager', 'system-admin');

        $line->update(['variance_reason_code_id' => null]); // clear hold
        activity()->performedOn($po)->causedBy($by)
            ->log("Excess authorised on GRN line {$line->id}");
    }

    public function closeShort(PurchaseOrder $po, User $by, string $reason): void
    {
        $this->assertRole($by, 'warehouse-manager', 'system-admin');
        $this->assertStatus($po, PurchaseOrderStatus::PartiallyReceived, PurchaseOrderStatus::ReceivingInProgress);

        DB::transaction(function () use ($po, $by, $reason) {
            $po->status      = PurchaseOrderStatus::PendingPutaway;
            $po->received_at = now();
            $po->save();

            activity()->performedOn($po)->causedBy($by)
                ->withProperties(['reason' => $reason])
                ->log("Closed short: {$reason}");
        });
    }

    // ─── Approver verification ────────────────────────────────────────────────

    /**
     * Direct close by warehouse manager — bypasses validation/verification steps.
     * Used when all GRNs are closed and the internal workflow can be skipped.
     */
    public function closeOrder(PurchaseOrder $po, User $by): void
    {
        $this->assertRole($by, 'warehouse-manager', 'system-admin');

        $allowed = [
            PurchaseOrderStatus::PendingPutaway,
            PurchaseOrderStatus::PutawayInProgress,
            PurchaseOrderStatus::PendingSupervisorValidation,
            PurchaseOrderStatus::CompletedPendingVerification,
            PurchaseOrderStatus::IssueRaised,
            PurchaseOrderStatus::IssueResolved,
        ];

        if (! in_array($po->status, $allowed, true)) {
            throw new \DomainException("Cannot close order from status: {$po->status->label()}");
        }

        DB::transaction(function () use ($po, $by) {
            $po->status      = PurchaseOrderStatus::VerifiedClosed;
            $po->verified_at ??= now();
            $po->save();

            activity()->performedOn($po)->causedBy($by)->log('Order closed by warehouse manager');
        });
    }

    public function verifyAndClose(PurchaseOrder $po, User $by): void
    {
        $this->assertStatus($po, PurchaseOrderStatus::CompletedPendingVerification, PurchaseOrderStatus::IssueResolved);
        $this->assertIsNotifiedApprover($po, $by);

        DB::transaction(function () use ($po, $by) {
            $po->status      = PurchaseOrderStatus::VerifiedClosed;
            $po->verified_at = now();
            $po->save();

            activity()->performedOn($po)->causedBy($by)->log('Verified and closed by approver');

            // Notify creator, clerk, supervisor
            foreach ([$po->requestedBy, $po->assignedClerk, $po->assignedSupervisor] as $notify) {
                $notify?->notify(new PoStatusChangedNotification(
                    $po, 'verified_closed',
                    "PO {$po->number} has been verified and closed.",
                ));
            }
        });
    }

    public function raiseIssue(PurchaseOrder $po, User $by, string $description, ?string $attachmentPath = null): PoIssue
    {
        $this->assertStatus($po, PurchaseOrderStatus::CompletedPendingVerification, PurchaseOrderStatus::IssueResolved);
        $this->assertIsNotifiedApprover($po, $by);

        return DB::transaction(function () use ($po, $by, $description, $attachmentPath) {
            $issue = PoIssue::create([
                'purchase_order_id' => $po->id,
                'raised_by'         => $by->id,
                'description'       => $description,
                'attachment_path'   => $attachmentPath,
                'status'            => PoIssueStatus::Open,
            ]);

            $po->status = PurchaseOrderStatus::IssueRaised;
            $po->save();

            activity()->performedOn($po)->causedBy($by)
                ->withProperties(['issue_id' => $issue->id])
                ->log('Issue raised by approver');

            // Notify warehouse-manager and assigned clerk
            $managers = User::role('warehouse-manager')->get();
            foreach ($managers as $manager) {
                $manager->notify(new PoStatusChangedNotification(
                    $po, 'issue_raised',
                    "An issue has been raised on PO {$po->number}.",
                    $description,
                ));
            }
            $po->assignedClerk?->notify(new PoStatusChangedNotification(
                $po, 'issue_raised',
                "An issue has been raised on PO {$po->number}.",
                $description,
            ));

            return $issue;
        });
    }

    public function resolveIssue(PoIssue $issue, User $by, string $notes, ?int $stockAdjustmentId = null): void
    {
        $this->assertRole($by, 'warehouse-manager', 'system-admin');

        if (! $issue->isOpen()) {
            throw new \DomainException("Issue #{$issue->id} is already resolved.");
        }

        DB::transaction(function () use ($issue, $by, $notes, $stockAdjustmentId) {
            $issue->update([
                'status'              => PoIssueStatus::Resolved,
                'resolved_by'         => $by->id,
                'resolution_notes'    => $notes,
                'stock_adjustment_id' => $stockAdjustmentId,
                'resolved_at'         => now(),
            ]);

            $po = $issue->purchaseOrder;

            // Only advance if no other open issues remain
            if (! $po->issues()->where('status', 'open')->exists()) {
                $po->status = PurchaseOrderStatus::IssueResolved;
                $po->save();

                activity()->performedOn($po)->causedBy($by)->log('All issues resolved');

                // Notify all notified approvers to verify again
                $this->notifyApproversForVerification($po);
            }
        });
    }

    // ─── Cancel ───────────────────────────────────────────────────────────────

    public function cancel(PurchaseOrder $po, string $reason, User $by): void
    {
        if (! $po->status->isCancellable()) {
            throw new InvalidTransitionException($po->status->value, PurchaseOrderStatus::Cancelled->value);
        }

        $po->status       = PurchaseOrderStatus::Cancelled;
        $po->cancelled_at = now();
        $po->notes        = ltrim(($po->notes ?? '') . "\nCancelled: {$reason}", "\n");
        $po->save();

        activity()->performedOn($po)->causedBy($by)->log("Cancelled: {$reason}");
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function assertStatus(PurchaseOrder $po, PurchaseOrderStatus ...$allowed): void
    {
        if (! in_array($po->status, $allowed, true)) {
            $labels = implode(', ', array_map(fn ($s) => $s->label(), $allowed));
            throw new \DomainException(
                "PO must be in [{$labels}]. Current: {$po->status->label()}.",
            );
        }
    }

    private function assertRole(User $user, string ...$roles): void
    {
        if (! $user->hasAnyRole($roles)) {
            $list = implode(', ', $roles);
            throw new \DomainException("User must have one of roles: {$list}.");
        }
    }

    private function assertCanValidate(PurchaseOrder $po, User $by): void
    {
        $canValidate = $by->hasAnyRole(['system-admin', 'warehouse-manager'])
            || $by->id === $po->assigned_supervisor_id;

        if (! $canValidate) {
            throw new \DomainException('Only the assigned supervisor or warehouse manager can validate.');
        }
    }

    private function assertIsNotifiedApprover(PurchaseOrder $po, User $by): void
    {
        if ($by->hasAnyRole(['system-admin'])) {
            return;
        }

        // Must be a user who appeared in the approval chain for this PO
        $wasApprover = \App\Models\PurchaseOrderApproval::where('purchase_order_id', $po->id)
            ->where('user_id', $by->id)
            ->exists();

        if (! $wasApprover) {
            throw new \DomainException('Only notified approvers can verify or raise issues on this PO.');
        }
    }

    private function notifyNextApprovers(PurchaseOrder $po): void
    {
        $deptLevel = $this->chainService->currentDepartmentLevel($po);

        if ($deptLevel !== null) {
            $users = $this->chainService->usersForDepartmentLevel($po, $deptLevel);
            foreach ($users as $user) {
                $user->notify(new PoApprovalRequestedNotification($po, $deptLevel));
            }
            return;
        }

        $pending = $this->chainService->pendingCategoryApprovals($po);

        if ($pending->isNotEmpty()) {
            // Notify category-level users
            foreach ($pending as $categoryId) {
                $users = $this->chainService->usersForCategory($categoryId, $po->warehouse_id);
                foreach ($users as $user) {
                    $user->notify(new PoApprovalRequestedNotification($po, 99));
                }
            }
            return;
        }

        // No chain configured — notify all warehouse managers as fallback approvers
        $managers = User::role('warehouse-manager')->get();
        foreach ($managers as $manager) {
            $manager->notify(new PoApprovalRequestedNotification($po, 0));
        }
    }

    private function notifyApproversForVerification(PurchaseOrder $po): void
    {
        $approverIds = \App\Models\PurchaseOrderApproval::where('purchase_order_id', $po->id)
            ->where('action', PurchaseOrderApprovalAction::Approved->value)
            ->pluck('user_id')
            ->unique();

        $approvers = User::whereIn('id', $approverIds)->get();
        foreach ($approvers as $approver) {
            $approver->notify(new PoStatusChangedNotification(
                $po, 'completed',
                "PO {$po->number} is complete and awaiting your verification.",
            ));
        }
    }

    private function areAllLinesReceived(PurchaseOrder $po): bool
    {
        $po->loadMissing('lines');
        if ($po->lines->isEmpty()) {
            return false;
        }

        // Sum expected qty across all lines
        $totalExpected = $po->lines->sum(fn ($l) => (float) $l->qty);

        // Sum received good + damaged across all confirmed GRN lines for this PO
        $totalReceived = \App\Models\GrnLine::whereHas('grn', fn ($q) =>
            $q->where('purchase_order_id', $po->id)
              ->whereIn('status', ['received', 'putaway_in_progress', 'putaway_complete', 'closed'])
        )->selectRaw('SUM(received_good_qty + received_damaged_qty) as total')
         ->value('total') ?? 0;

        return (float) $totalReceived >= $totalExpected - 0.0001;
    }
}
