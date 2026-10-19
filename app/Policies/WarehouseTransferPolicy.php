<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Transfers\WarehouseTransferStatus;
use App\Models\User;
use App\Models\WarehouseTransfer;

class WarehouseTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('transfers.view');
    }

    public function view(User $user, WarehouseTransfer $wtr): bool
    {
        return $user->can('transfers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('transfers.create');
    }

    public function submit(User $user, WarehouseTransfer $wtr): bool
    {
        return $user->can('transfers.create')
            && $wtr->status === WarehouseTransferStatus::Draft
            && $wtr->created_by === $user->id;
    }

    /**
     * Accept a submitted warehouse transfer.
     *
     * The accepting user must have transfers.accept AND the transfer must be
     * in Submitted status. warehouse-manager / system-admin can always accept;
     * others must have access to the destination VW.
     */
    public function accept(User $user, WarehouseTransfer $wtr): bool
    {
        if (! $user->can('transfers.accept')) {
            return false;
        }

        if ($wtr->status !== WarehouseTransferStatus::Submitted) {
            return false;
        }

        if ($user->hasAnyRole(['warehouse-manager', 'system-admin'])) {
            return true;
        }

        if ($wtr->to_virtual_warehouse_id === null) {
            return false;
        }

        return in_array(
            $wtr->to_virtual_warehouse_id,
            $user->accessibleVirtualWarehouseIds(),
            true
        );
    }

    /**
     * Dispatch an accepted warehouse transfer (move stock to in-transit).
     */
    public function dispatch(User $user, WarehouseTransfer $wtr): bool
    {
        $allowedStatuses = [WarehouseTransferStatus::Accepted, WarehouseTransferStatus::Draft];

        return $user->can('transfers.execute')
            && in_array($wtr->status, $allowedStatuses, true);
    }

    /**
     * Receive a dispatched warehouse transfer at the destination.
     */
    public function receive(User $user, WarehouseTransfer $wtr): bool
    {
        return $user->can('transfers.execute')
            && $wtr->status === WarehouseTransferStatus::Dispatched;
    }

    /**
     * Reject a submitted or accepted warehouse transfer.
     */
    public function reject(User $user, WarehouseTransfer $wtr): bool
    {
        $rejectableStatuses = [WarehouseTransferStatus::Submitted, WarehouseTransferStatus::Accepted];

        return $user->can('transfers.accept')
            && in_array($wtr->status, $rejectableStatuses, true);
    }

    /**
     * Cancel a warehouse transfer that is not yet in a terminal state.
     *
     * The creator may cancel their own non-terminal transfers; users with
     * transfers.accept can cancel any non-terminal transfer.
     */
    public function cancel(User $user, WarehouseTransfer $wtr): bool
    {
        $terminalStatuses = [
            WarehouseTransferStatus::Dispatched,
            WarehouseTransferStatus::Completed,
            WarehouseTransferStatus::Rejected,
            WarehouseTransferStatus::Cancelled,
        ];

        if (in_array($wtr->status, $terminalStatuses, true)) {
            return false;
        }

        if ($user->can('transfers.accept')) {
            return true;
        }

        return $user->can('transfers.create') && $wtr->created_by === $user->id;
    }
}
