<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InternalMovement;
use App\Models\User;

class InternalMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('movements.view');
    }

    public function view(User $user, InternalMovement $movement): bool
    {
        return $user->can('movements.view');
    }

    public function create(User $user): bool
    {
        return $user->can('movements.create');
    }

    public function post(User $user, InternalMovement $movement): bool
    {
        return $user->can('movements.create') && $movement->status->value === 'draft';
    }

    public function cancel(User $user, InternalMovement $movement): bool
    {
        return $user->can('movements.create') && $movement->status->value === 'draft';
    }
}
