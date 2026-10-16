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

    public function approve(User $user, StockTransfer $transfer): bool
    {
        return $user->can('transfers.approve')
            && $transfer->status === TransferStatus::Submitted;
    }

    public function reject(User $user, StockTransfer $transfer): bool
    {
        return $user->can('transfers.approve')
            && $transfer->status === TransferStatus::Submitted;
    }
}
