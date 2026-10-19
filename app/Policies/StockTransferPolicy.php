<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TransferStatus;
use App\Models\StockTransfer;
use App\Models\User;

class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('transfers.view');
    }

    public function view(User $user, StockTransfer $transfer): bool
    {
        return $user->can('transfers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('transfers.create');
    }

    public function submit(User $user, StockTransfer $transfer): bool
    {
        return $user->can('transfers.create')
            && $transfer->status === TransferStatus::Draft
            && $transfer->created_by === $user->id;
    }

    /**
     * Accept a submitted transfer (replaces approve).
     *
     * The accepting user must have the transfers.accept permission AND must be
     * a warehouse-manager, system-admin, OR have direct access to the target
     * virtual warehouse.
     */
    public function accept(User $user, StockTransfer $transfer): bool
    {
        if (! $user->can('transfers.accept')) {
            return false;
        }

        if ($transfer->status !== TransferStatus::Submitted) {
            return false;
        }

        // warehouse-manager and system-admin can accept any transfer
        if ($user->hasAnyRole(['warehouse-manager', 'system-admin'])) {
            return true;
        }

        // Other users may accept only if they have access to the destination VW
        if ($transfer->to_virtual_warehouse_id === null) {
            return false;
        }

        return in_array(
            $transfer->to_virtual_warehouse_id,
            $user->accessibleVirtualWarehouseIds(),
            true
        );
    }

    /**
     * Backward-compatible alias for accept().
     *
     * @deprecated Use accept() instead.
     */
    public function approve(User $user, StockTransfer $transfer): bool
    {
        return $this->accept($user, $transfer);
    }

    /**
     * Execute (post) an accepted transfer.
     */
    public function execute(User $user, StockTransfer $transfer): bool
    {
        return $user->can('transfers.execute')
            && $transfer->status === TransferStatus::Accepted;
    }

    /**
     * Reject a submitted or accepted transfer.
     */
    public function reject(User $user, StockTransfer $transfer): bool
    {
        $rejectableStatuses = [TransferStatus::Submitted, TransferStatus::Accepted];

        return $user->can('transfers.accept')
            && in_array($transfer->status, $rejectableStatuses, true);
    }
}
