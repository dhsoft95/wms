<?php

declare(strict_types=1);

namespace App\Services\Inbound;

use App\Enums\Inbound\ApprovalChainDocumentType;
use App\Enums\Inbound\PurchaseOrderApprovalAction;
use App\Enums\Inbound\PurchaseOrderStatus;
use App\Models\ApprovalChain;
use App\Models\ApprovalChainUser;
use App\Models\ItemCategoryUser;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderApproval;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Manages the multi-level approval chain for Purchase Orders.
 *
 * Chain progression:
 *   1. Department levels (level 1..n) — any one user at the level can act
 *   2. Category level — each item category in the lines needs at least one
 *      of its ItemCategoryUser approvers for this warehouse to approve
 *   3. If no chain configured and no category users → immediate approval
 */
final class ApprovalChainService
{
    // ─── Resolution ──────────────────────────────────────────────────────────

    /**
     * Returns the ordered department-level chains for a PO's department.
     */
    public function getDepartmentChains(PurchaseOrder $po): Collection
    {
        if ($po->department_id === null) {
            return collect();
        }

        return ApprovalChain::where('department_id', $po->department_id)
            ->where('document_type', ApprovalChainDocumentType::Po->value)
            ->with('chainUsers.user')
            ->orderBy('level')
            ->get();
    }

    /**
     * Returns users who have already approved at a given department level.
     */
    public function approvedUsersAtLevel(PurchaseOrder $po, int $level): Collection
    {
        return PurchaseOrderApproval::where('purchase_order_id', $po->id)
            ->where('level', $level)
            ->whereNotNull('approval_chain_id')
            ->where('action', PurchaseOrderApprovalAction::Approved->value)
            ->pluck('user_id');
    }

    /**
     * Returns the current department approval level waiting for action,
     * or null if department levels are complete (or none exist).
     */
    public function currentDepartmentLevel(PurchaseOrder $po): ?int
    {
        $chains = $this->getDepartmentChains($po);
        if ($chains->isEmpty()) {
            return null;
        }

        foreach ($chains as $chain) {
            $alreadyApproved = PurchaseOrderApproval::where('purchase_order_id', $po->id)
                ->where('approval_chain_id', $chain->id)
                ->where('action', PurchaseOrderApprovalAction::Approved->value)
                ->exists();

            if (! $alreadyApproved) {
                return $chain->level;
            }
        }

        return null; // all department levels approved
    }

    /**
     * Returns users who should be notified for a given department approval level.
     */
    public function usersForDepartmentLevel(PurchaseOrder $po, int $level): Collection
    {
        $chain = ApprovalChain::where('department_id', $po->department_id)
            ->where('document_type', ApprovalChainDocumentType::Po->value)
            ->where('level', $level)
            ->first();

        if ($chain === null) {
            return collect();
        }

        return $chain->users;
    }

    /**
     * Returns item category IDs in the PO lines that still need category approval.
     * Skips categories that have no assigned category users for this warehouse.
     */
    public function pendingCategoryApprovals(PurchaseOrder $po): Collection
    {
        $po->loadMissing('lines.item');

        $categoryIds = $po->lines
            ->pluck('item.category_id')
            ->filter()
            ->unique();

        $alreadyApprovedCategoryIds = PurchaseOrderApproval::where('purchase_order_id', $po->id)
            ->whereNull('approval_chain_id')
            ->where('action', PurchaseOrderApprovalAction::Approved->value)
            ->pluck('item_category_id');

        $pending = collect();
        foreach ($categoryIds->diff($alreadyApprovedCategoryIds) as $categoryId) {
            $hasUsers = ItemCategoryUser::where('item_category_id', $categoryId)
                ->where('warehouse_id', $po->warehouse_id)
                ->exists();

            if ($hasUsers) {
                $pending->push($categoryId);
            }
        }

        return $pending;
    }

    /**
     * Returns category users who should be notified for a given category.
     */
    public function usersForCategory(int $categoryId, int $warehouseId): Collection
    {
        return ItemCategoryUser::where('item_category_id', $categoryId)
            ->where('warehouse_id', $warehouseId)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();
    }

    // ─── Guard checks ─────────────────────────────────────────────────────────

    /**
     * Returns true if the given user can act at the current department level.
     */
    public function canActAtCurrentLevel(PurchaseOrder $po, User $user): bool
    {
        $level = $this->currentDepartmentLevel($po);
        if ($level === null) {
            return false;
        }

        $chain = ApprovalChain::where('department_id', $po->department_id)
            ->where('document_type', ApprovalChainDocumentType::Po->value)
            ->where('level', $level)
            ->first();

        if ($chain === null) {
            return false;
        }

        return ApprovalChainUser::where('approval_chain_id', $chain->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Returns true if the user is a category approver for at least one pending category.
     */
    public function canActOnCategoryLevel(PurchaseOrder $po, User $user): bool
    {
        $pendingCategoryIds = $this->pendingCategoryApprovals($po);
        if ($pendingCategoryIds->isEmpty()) {
            return false;
        }

        if ($user->hasRole('system-admin')) {
            return true;
        }

        return ItemCategoryUser::where('user_id', $user->id)
            ->where('warehouse_id', $po->warehouse_id)
            ->whereIn('item_category_id', $pendingCategoryIds)
            ->exists();
    }

    /**
     * Returns true when no dept chain and no category users are configured,
     * and the user is a warehouse manager or system admin.
     * In this case the manager acts as the default approver.
     */
    public function canActAsFallbackApprover(PurchaseOrder $po, User $user): bool
    {
        if (! $user->hasAnyRole(['warehouse-manager', 'system-admin'])) {
            return false;
        }

        return $this->getDepartmentChains($po)->isEmpty()
            && $this->pendingCategoryApprovals($po)->isEmpty();
    }

    // ─── Progression ─────────────────────────────────────────────────────────

    /**
     * Checks whether all approvals (department + category) are complete.
     * Returns true when the PO should advance to ApprovedPendingAcceptance.
     */
    public function isFullyApproved(PurchaseOrder $po): bool
    {
        return $this->currentDepartmentLevel($po) === null
            && $this->pendingCategoryApprovals($po)->isEmpty();
    }

    /**
     * Record an approval action by a user.
     * For department-level approvals, pass the approval_chain_id.
     * For category-level approvals, pass item_category_id with chain_id = null.
     */
    public function record(
        PurchaseOrder $po,
        User $user,
        PurchaseOrderApprovalAction $action,
        int $level,
        ?int $chainId = null,
        ?int $itemCategoryId = null,
        ?string $comment = null,
    ): PurchaseOrderApproval {
        return PurchaseOrderApproval::create([
            'purchase_order_id' => $po->id,
            'approval_chain_id' => $chainId,
            'item_category_id'  => $itemCategoryId,
            'level'             => $level,
            'user_id'           => $user->id,
            'action'            => $action->value,
            'comment'           => $comment,
        ]);
    }

    /**
     * Resets all approval records for this PO (used when returned for correction
     * and resubmitted — chain restarts from level 1).
     */
    public function resetApprovals(PurchaseOrder $po): void
    {
        PurchaseOrderApproval::where('purchase_order_id', $po->id)->delete();
    }
}
