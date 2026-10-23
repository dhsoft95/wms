<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Inbound\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderApproval;
use App\Models\User;
use App\Services\Inbound\ApprovalChainService;

class PurchaseOrderPolicy
{
    public function __construct(
        private readonly ApprovalChainService $chainService,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('po.view');
    }

    public function view(User $user, PurchaseOrder $po): bool
    {
        if (! $user->can('po.view')) {
            return false;
        }

        if ($user->hasAnyRole(['system-admin', 'warehouse-manager', 'viewer-auditor', 'data-analyst'])) {
            return true;
        }

        if ($user->hasRole('warehouse-supervisor')) {
            return $po->assigned_supervisor_id === $user->id;
        }

        if ($user->hasRole('storekeeper')) {
            return $po->assigned_clerk_id === $user->id;
        }

        if ($user->hasAnyRole(['department-head', 'department-manager'])) {
            return $po->department?->head_user_id === $user->id;
        }

        if ($user->hasRole('department-user')) {
            return $po->requested_by === $user->id
                || PurchaseOrderApproval::where('purchase_order_id', $po->id)
                    ->where('user_id', $user->id)
                    ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('po.create');
    }

    public function update(User $user, PurchaseOrder $po): bool
    {
        return $user->can('po.create')
            && $po->status->isEditable()
            && ($po->requested_by === $user->id || $user->hasAnyRole(['system-admin', 'warehouse-manager']));
    }

    public function submit(User $user, PurchaseOrder $po): bool
    {
        return $user->can('po.create')
            && $po->status->isEditable()
            && ($po->requested_by === $user->id || $user->hasAnyRole(['system-admin', 'warehouse-manager']));
    }

    public function approve(User $user, PurchaseOrder $po): bool
    {
        if (! $po->status->isAwaitingApproval()) {
            return false;
        }

        return $this->chainService->canActAtCurrentLevel($po, $user);
    }

    public function returnForCorrection(User $user, PurchaseOrder $po): bool
    {
        return $this->approve($user, $po);
    }

    public function reject(User $user, PurchaseOrder $po): bool
    {
        return $this->approve($user, $po);
    }

    public function accept(User $user, PurchaseOrder $po): bool
    {
        return $user->can('po.accept')
            && $po->status === PurchaseOrderStatus::ApprovedPendingAcceptance
            && $user->hasAnyRole(['warehouse-manager', 'system-admin']);
    }

    public function rejectByWarehouse(User $user, PurchaseOrder $po): bool
    {
        return $this->accept($user, $po);
    }

    public function assignSupervisor(User $user, PurchaseOrder $po): bool
    {
        return $user->can('po.assign')
            && $po->status === PurchaseOrderStatus::AcceptedPendingAssignment
            && $user->hasAnyRole(['warehouse-manager', 'system-admin']);
    }

    public function assignClerk(User $user, PurchaseOrder $po): bool
    {
        return $user->can('po.assign')
            && $po->status === PurchaseOrderStatus::AssignedPendingReceiving
            && $user->hasAnyRole(['warehouse-manager', 'warehouse-supervisor', 'system-admin']);
    }

    public function receive(User $user, PurchaseOrder $po): bool
    {
        if (! $user->can('po.receive')) {
            return false;
        }

        if (! in_array($po->status, [
            PurchaseOrderStatus::AssignedPendingReceiving,
            PurchaseOrderStatus::ReceivingInProgress,
            PurchaseOrderStatus::PartiallyReceived,
        ], true)) {
            return false;
        }

        if ($user->hasAnyRole(['system-admin', 'warehouse-manager', 'warehouse-supervisor'])) {
            return true;
        }

        return $po->assigned_clerk_id === $user->id;
    }

    public function validate(User $user, PurchaseOrder $po): bool
    {
        return $user->can('po.validate')
            && $po->status === PurchaseOrderStatus::PendingSupervisorValidation
            && ($po->assigned_supervisor_id === $user->id
                || $user->hasAnyRole(['warehouse-manager', 'system-admin']));
    }

    public function verify(User $user, PurchaseOrder $po): bool
    {
        if (! $user->can('po.verify')) {
            return false;
        }

        if (! in_array($po->status, [
            PurchaseOrderStatus::CompletedPendingVerification,
            PurchaseOrderStatus::IssueResolved,
        ], true)) {
            return false;
        }

        if ($user->hasRole('system-admin')) {
            return true;
        }

        return PurchaseOrderApproval::where('purchase_order_id', $po->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function raiseIssue(User $user, PurchaseOrder $po): bool
    {
        return $this->verify($user, $po);
    }

    public function authoriseExcess(User $user, PurchaseOrder $po): bool
    {
        return $user->can('po.excess.approve')
            && $user->hasAnyRole(['warehouse-manager', 'system-admin']);
    }

    public function closeShort(User $user, PurchaseOrder $po): bool
    {
        return $user->can('po.close.short')
            && $user->hasAnyRole(['warehouse-manager', 'system-admin'])
            && in_array($po->status, [
                PurchaseOrderStatus::PartiallyReceived,
                PurchaseOrderStatus::ReceivingInProgress,
            ], true);
    }

    public function resolveIssue(User $user, PurchaseOrder $po): bool
    {
        return $user->can('po.verify')
            && $po->status === PurchaseOrderStatus::IssueRaised
            && $user->hasAnyRole(['warehouse-manager', 'system-admin']);
    }

    public function assign(User $user, PurchaseOrder $po): bool
    {
        return $this->assignSupervisor($user, $po) || $this->assignClerk($user, $po);
    }

    public function cancel(User $user, PurchaseOrder $po): bool
    {
        return $user->can('po.create')
            && $po->status->isCancellable()
            && ($po->requested_by === $user->id || $user->hasAnyRole(['warehouse-manager', 'system-admin']));
    }
}
