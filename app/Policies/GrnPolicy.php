<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\GrnStatus;
use App\Models\Grn;
use App\Models\User;

class GrnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('grn.view');
    }

    public function view(User $user, Grn $grn): bool
    {
        return $user->can('grn.view');
    }

    public function create(User $user): bool
    {
        return $user->can('grn.create');
    }

    public function update(User $user, Grn $grn): bool
    {
        return $user->can('grn.create') && $grn->isEditable();
    }

    public function receive(User $user, Grn $grn): bool
    {
        return $user->can('grn.receive')
            && in_array($grn->status, [GrnStatus::Draft, GrnStatus::Receiving], true);
    }

    public function confirm(User $user, Grn $grn): bool
    {
        return $user->can('grn.confirm')
            && in_array($grn->status, [GrnStatus::Receiving, GrnStatus::PendingVarianceApproval], true);
    }

    public function approveVariance(User $user, Grn $grn): bool
    {
        if ($grn->status !== GrnStatus::PendingVarianceApproval) {
            return false;
        }

        return $user->hasRole('system-admin') || $user->can('grn.variance.approve');
    }

    public function cancel(User $user, Grn $grn): bool
    {
        return $user->can('grn.cancel') && $grn->status->isActive();
    }

    public function close(User $user, Grn $grn): bool
    {
        return $user->can('grn.close')
            && in_array($grn->status, [GrnStatus::PutawayComplete, GrnStatus::Received], true);
    }
}
