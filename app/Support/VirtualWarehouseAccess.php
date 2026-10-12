<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Central check for VW assignment. Used by BelongsToVirtualWarehouse and policies.
 */
class VirtualWarehouseAccess
{
    /**
     * Throw an AuthorizationException if $user is not assigned to $vwId.
     * Unrestricted roles always pass.
     */
    public static function ensure(User $user, int $vwId): void
    {
        if ($user->canAccessAllVirtualWarehouses()) {
            return;
        }

        if (! in_array($vwId, $user->accessibleVirtualWarehouseIds(), strict: true)) {
            throw new AuthorizationException(
                'You are not assigned to the requested virtual warehouse.'
            );
        }
    }
}
