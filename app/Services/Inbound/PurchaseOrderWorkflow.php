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
        $this->assertStatus($po, PurchaseOrderStatus::Draft, PurchaseOrderStatus::AwaitingRequesterCorrection, PurchaseOrderStatus::ReturnedForCorrection);

        if ($po->status === PurchaseOrderStatus::ReturnedForCorrection) {
            if (! $by->hasRole('department-head') || $po->department?->head_user_id !== $by->id) {
                throw new \DomainException('Only the department head may resubmit an order in returned-for-correction status.');
            }
        } elseif ($by->id !== $po->requested_by && ! $by->hasAnyRole(['system-admin', 'warehouse-manager', 'department-head', 'department-user'])) {
            throw new \DomainException('Only the creator may submit this purchase order.');
        }

        DB::transaction(function () use ($po, $by) {
            if (in_array($po->status, [PurchaseOrderStatus::AwaitingRequesterCorrection, PurchaseOrderStatus::ReturnedForCorrection], true)) {
                $this->chainService->resetApprovals($po);
            }

            $po->submitted_at = now();
            $po->status       = PurchaseOrderStatus::PendingApproval;
            $po->save();

            activity()->performedOn($po)->causedBy($by)->log('Submitted for approval');
        });

        $this->notifyNextApprovers($po);
    }

    public function approveAtLevel(
        PurchaseOrder $po,
        User $by,
        ?string $comment = null,
    ): void {
        $this->assertStatus($po, PurchaseOrderStatus::PendingApproval);

        if (! $this->chainService->canActAtCurrentLevel($po, $by)) {
            throw new \DomainException('You are not authorised to approve this purchase order.');
        }

        DB::transaction(function () use ($po, $by, $comment) {
            $this->chainService->record(
                $po, $by, PurchaseOrderApprovalAction::Approved, 1, null, null, $comment,
            );
            $po->status      = PurchaseOrderStatus::ApprovedPendingAcceptance;
            $po->approved_at = now();
            $po->save();

            activity()->performedOn($po)->causedBy($by)->log('Approved by dept head');
        });

        User::role('warehouse-manager')->each(fn ($m) =>
            $this->tryNotify($m, new PoStatusChangedNotification(
                $po, 'approved',
                "PO {$po->number} has been approved and is awaiting your acceptance.",
            ))
        );
    }

    /**
     * Send the PO back to the requester for correction.
     * There is only one approval level (dept head), so this always returns to the requester.
     */
    public function returnToPreviousLevel(PurchaseOrder $po, User $by, string $comment): void
    {
        $this->returnForCorrection($po, $by, $comment);
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
        });

        $this->tryNotify($po->requestedBy, new PoStatusChangedNotification(
            $po, 'returned',
            "PO {$po->number} has been returned for correction.",
            $comment,
        ));
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
        });

        $this->tryNotify($po->requestedBy, new PoStatusChangedNotification(
            $po, 'rejected',
            "PO {$po->number} has been rejected.",
            $reason,
        ));
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
        });

        $this->tryNotify($po->requestedBy, new PoStatusChangedNotification(
            $po, 'accepted',
            "PO {$po->number} has been accepted by the warehouse.",
        ));
    }

    public function rejectByWarehouse(PurchaseOrder $po, User $by, string $reason): void
    {
        $this->assertStatus($po, PurchaseOrderStatus::ApprovedPendingAcceptance);
        $this->assertRole($by, 'warehouse-manager', 'system-admin');

        DB::transaction(function () use ($po, $by, $reason) {
            $po->status                      = PurchaseOrderStatus::ReturnedForCorrection;
            $po->acceptance_rejection_reason = $reason;
            $po->save();

            activity()->performedOn($po)->causedBy($by)
                ->withProperties(['reason' => $reason])
                ->log('Sent back for correction by warehouse manager');
        });

        $this->tryNotify($po->department?->headUser, new PoStatusChangedNotification(
            $po, 'returned',
            "PO {$po->number} has been sent back for correction by the warehouse. Please review and forward to the requester.",
            $reason,
        ));
    }

    /**
     * HOD forwards the return to the requester — transitions to AwaitingRequesterCorrection
     * so the HOD's "Send Back" button disappears and the requester gains edit/resubmit access.
     */
    public function forwardReturnToRequester(PurchaseOrder $po, User $by, string $comment): void
    {
        $this->assertStatus($po, PurchaseOrderStatus::ReturnedForCorrection);

        if ($po->department?->head_user_id !== $by->id && ! $by->hasRole('system-admin')) {
            throw new \DomainException('Only the department head may forward this return.');
        }

        DB::transaction(function () use ($po, $by, $comment) {
            $this->chainService->record($po, $by, PurchaseOrderApprovalAction::Returned, 1, null, null, $comment);

            $po->status = PurchaseOrderStatus::AwaitingRequesterCorrection;
            $po->save();

            activity()->performedOn($po)->causedBy($by)
                ->withProperties(['comment' => $comment])
                ->log('Return forwarded to requester by dept head');
        });

        $this->tryNotify($po->requestedBy, new PoStatusChangedNotification(
            $po, 'returned',
            "PO {$po->number} has been forwarded to you for correction by the department head.",
            $comment,
        ));
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
        });

        $this->tryNotify($supervisor, new PoStatusChangedNotification(
            $po, 'supervisor_assigned',
            "PO {$po->number} has been assigned to you as supervisor.",
        ));
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
        });

        $this->tryNotify($clerk, new PoStatusChangedNotification(
            $po, 'clerk_assigned',
            "PO {$po->number} has been assigned to you for receiving.",
        ));
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
            $this->tryNotify($po->requestedBy, new PoStatusChangedNotification(
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

        $po->status = PurchaseOrderStatus::CompletedPendingVerification;
        $po->save();

        activity()->performedOn($po)->causedBy($by)->log('Putaway complete — awaiting GRN close');
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
        });

        $this->notifyApproversForVerification($po);
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

        $this->tryNotify($po->assignedClerk, new PoStatusChangedNotification(
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
        });

        foreach ([$po->requestedBy, $po->assignedClerk, $po->assignedSupervisor] as $recipient) {
            $this->tryNotify($recipient, new PoStatusChangedNotification(
                $po, 'verified_closed',
                "PO {$po->number} has been verified and closed.",
            ));
        }
    }

    public function raiseIssue(PurchaseOrder $po, User $by, string $description, ?string $attachmentPath = null): PoIssue
    {
        $this->assertStatus($po, PurchaseOrderStatus::CompletedPendingVerification, PurchaseOrderStatus::IssueResolved);
        $this->assertIsNotifiedApprover($po, $by);

        $issue = DB::transaction(function () use ($po, $by, $description, $attachmentPath) {
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

            return $issue;
        });

        User::role('warehouse-manager')->each(fn ($m) =>
            $this->tryNotify($m, new PoStatusChangedNotification(
                $po, 'issue_raised',
                "An issue has been raised on PO {$po->number}.",
                $description,
            ))
        );
        $this->tryNotify($po->assignedClerk, new PoStatusChangedNotification(
            $po, 'issue_raised',
            "An issue has been raised on PO {$po->number}.",
            $description,
        ));

        return $issue;
    }

    public function resolveIssue(PoIssue $issue, User $by, string $notes, ?int $stockAdjustmentId = null): void
    {
        $this->assertRole($by, 'warehouse-manager', 'system-admin');

        if (! $issue->isOpen()) {
            throw new \DomainException("Issue #{$issue->id} is already resolved.");
        }

        $allResolved = false;

        DB::transaction(function () use ($issue, $by, $notes, $stockAdjustmentId, &$allResolved) {
            $issue->update([
                'status'              => PoIssueStatus::Resolved,
                'resolved_by'         => $by->id,
                'resolution_notes'    => $notes,
                'stock_adjustment_id' => $stockAdjustmentId,
                'resolved_at'         => now(),
            ]);

            $po = $issue->purchaseOrder;

            if (! $po->issues()->where('status', 'open')->exists()) {
                $po->status = PurchaseOrderStatus::IssueResolved;
                $po->save();

                activity()->performedOn($po)->causedBy($by)->log('All issues resolved');
                $allResolved = true;
            }
        });

        if ($allResolved) {
            $this->notifyApproversForVerification($issue->purchaseOrder);
        }
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
        if ($po->status === PurchaseOrderStatus::PendingApproval) {
            $this->tryNotify($po->department?->headUser, new PoApprovalRequestedNotification($po, 1));
        }
    }

    private function notifyApproversForVerification(PurchaseOrder $po): void
    {
        $approverIds = \App\Models\PurchaseOrderApproval::where('purchase_order_id', $po->id)
            ->where('action', PurchaseOrderApprovalAction::Approved->value)
            ->pluck('user_id')
            ->unique();

        User::whereIn('id', $approverIds)->each(fn ($approver) =>
            $this->tryNotify($approver, new PoStatusChangedNotification(
                $po, 'completed',
                "PO {$po->number} is complete and awaiting your verification.",
            ))
        );
    }

    private function tryNotify(?User $user, mixed $notification): void
    {
        if ($user === null) {
            return;
        }
        try {
            $user->notify($notification);
        } catch (\Throwable) {
            // Email failures must not block workflow actions
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
