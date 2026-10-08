<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    // system-admin bypasses via Gate::before — only wh-manager+ reaches these methods

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo('users.manage');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasPermissionTo('users.manage') || $actor->is($target);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo('users.manage');
    }

    public function update(User $actor, User $target): bool
    {
        if (! $actor->hasPermissionTo('users.manage')) {
            return $actor->is($target); // users may edit their own profile
        }

        // Admins cannot demote themselves or be demoted by other admins
        if ($target->hasRole('system-admin') && ! $actor->is($target)) {
            return false;
        }

        return true;
    }

    public function delete(User $actor, User $target): bool
    {
        if (! $actor->hasPermissionTo('users.manage')) {
            return false;
        }

        // Cannot delete yourself or the last system-admin
        if ($actor->is($target)) {
            return false;
        }

        if ($target->hasRole('system-admin')) {
            return User::role('system-admin')->where('status', 'active')->count() > 1;
        }

        return true;
    }

    public function suspend(User $actor, User $target): bool
    {
        if (! $actor->hasPermissionTo('users.manage') || $actor->is($target)) {
            return false;
        }

        if ($target->hasRole('system-admin')) {
            return User::role('system-admin')->where('status', 'active')->count() > 1;
        }

        return true;
    }

    public function resendInvitation(User $actor, User $target): bool
    {
        return $actor->hasPermissionTo('users.manage') && $target->isInvited();
    }

    public function resetTwoFactor(User $actor, User $target): bool
    {
        return $actor->hasPermissionTo('users.manage') || $actor->is($target);
    }
}
