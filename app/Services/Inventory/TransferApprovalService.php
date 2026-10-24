<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inbound\ApprovalChainDocumentType;
use App\Models\ApprovalChain;
use App\Models\StockTransfer;
use App\Models\StockTransferApproval;
use App\Models\User;
use Illuminate\Support\Collection;

final class TransferApprovalService
{
    public function getDepartmentChains(StockTransfer $transfer): Collection
    {
        if ($transfer->department_id === null) {
            return collect();
        }

        return ApprovalChain::where('department_id', $transfer->department_id)
            ->where('document_type', ApprovalChainDocumentType::Transfer->value)
            ->with('chainUsers.user')
            ->orderBy('level')
            ->get();
    }

    public function currentPendingChain(StockTransfer $transfer): ?ApprovalChain
    {
        $chains = $this->getDepartmentChains($transfer);

        foreach ($chains as $chain) {
            $approved = StockTransferApproval::where('stock_transfer_id', $transfer->id)
                ->where('approval_chain_id', $chain->id)
                ->where('decision', 'approved')
                ->exists();

            if (! $approved) {
                return $chain;
            }
        }

        return null;
    }

    public function canActAtCurrentLevel(StockTransfer $transfer, User $user): bool
    {
        if ($user->hasRole('system-admin')) {
            return true;
        }

        $chain = $this->currentPendingChain($transfer);

        if ($chain !== null) {
            return $chain->users()->where('users.id', $user->id)->exists();
        }

        if ($transfer->department?->head_user_id !== null) {
            return $transfer->department->head_user_id === $user->id;
        }

        return $user->hasRole('warehouse-manager');
    }

    public function record(
        StockTransfer $transfer,
        User $user,
        string $decision,
        int $level,
        ?int $chainId,
        ?string $notes = null,
        string $approverRole = 'department',
    ): StockTransferApproval {
        return StockTransferApproval::create([
            'stock_transfer_id' => $transfer->id,
            'approval_chain_id' => $chainId,
            'level'             => $level,
            'approver_role'     => $approverRole,
            'decided_by'        => $user->id,
            'decision'          => $decision,
            'notes'             => $notes,
            'decided_at'        => now(),
        ]);
    }

    public function resetApprovals(StockTransfer $transfer): void
    {
        StockTransferApproval::where('stock_transfer_id', $transfer->id)->delete();
    }

    public function isFullyApproved(StockTransfer $transfer): bool
    {
        $chains = $this->getDepartmentChains($transfer);

        if ($chains->isEmpty()) {
            return true;
        }

        foreach ($chains as $chain) {
            $approved = StockTransferApproval::where('stock_transfer_id', $transfer->id)
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
