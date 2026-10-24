<?php

declare(strict_types=1);

namespace App\Services\Outbound;

use App\Enums\Inbound\ApprovalChainDocumentType;
use App\Models\ApprovalChain;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderApproval;
use App\Models\User;
use Illuminate\Support\Collection;

final class DoApprovalService
{
    public function getDepartmentChains(DeliveryOrder $do): Collection
    {
        if ($do->department_id === null) {
            return collect();
        }

        return ApprovalChain::where('department_id', $do->department_id)
            ->where('document_type', ApprovalChainDocumentType::Do->value)
            ->with('chainUsers.user')
            ->orderBy('level')
            ->get();
    }

    public function currentPendingChain(DeliveryOrder $do): ?ApprovalChain
    {
        $chains = $this->getDepartmentChains($do);

        foreach ($chains as $chain) {
            $approved = DeliveryOrderApproval::where('delivery_order_id', $do->id)
                ->where('approval_chain_id', $chain->id)
                ->where('decision', 'approved')
                ->exists();

            if (! $approved) {
                return $chain;
            }
        }

        return null;
    }

    public function canActAtCurrentLevel(DeliveryOrder $do, User $user): bool
    {
        if ($user->hasRole('system-admin')) {
            return true;
        }

        $chain = $this->currentPendingChain($do);

        if ($chain !== null) {
            // User must be in the approval chain users for this level
            return $chain->users()->where('users.id', $user->id)->exists();
        }

        // No chain configured — fall back to department head or warehouse-manager
        if ($do->department?->head_user_id !== null) {
            return $do->department->head_user_id === $user->id;
        }

        return $user->hasRole('warehouse-manager');
    }

    public function record(
        DeliveryOrder $do,
        User $user,
        string $decision,
        int $level,
        ?int $chainId,
        ?string $notes = null,
        string $approverRole = 'department',
    ): DeliveryOrderApproval {
        return DeliveryOrderApproval::create([
            'delivery_order_id' => $do->id,
            'approval_chain_id' => $chainId,
            'level'             => $level,
            'approver_role'     => $approverRole,
            'decided_by'        => $user->id,
            'decision'          => $decision,
            'notes'             => $notes,
            'decided_at'        => now(),
        ]);
    }

    public function resetApprovals(DeliveryOrder $do): void
    {
        DeliveryOrderApproval::where('delivery_order_id', $do->id)->delete();
    }

    public function isFullyApproved(DeliveryOrder $do): bool
    {
        $chains = $this->getDepartmentChains($do);

        if ($chains->isEmpty()) {
            return true;
        }

        foreach ($chains as $chain) {
            $approved = DeliveryOrderApproval::where('delivery_order_id', $do->id)
                ->where('approval_chain_id', $chain->id)
                ->where('decision', 'approved')
                ->exists();

            if (! $approved) {
                return false;
            }
        }

        return true;
    }
}
