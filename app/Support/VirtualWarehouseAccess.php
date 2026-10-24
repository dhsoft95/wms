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
     * Set to true to bypass the VW check during system-level ledger operations
     * (e.g. workflow posting triggered by a restricted user such as a department head).
     */
    public static bool $bypass = false;

    /**
     * Throw an AuthorizationException if $user is not assigned to $vwId.
     * Unrestricted roles and active bypass always pass.
     */
    public static function ensure(User $user, int $vwId): void
    {
        if (static::$bypass) {
            return;
        }

        if ($user->canAccessAllVirtualWarehouses()) {
            return;
        }

        if (! in_array($vwId, $user->accessibleVirtualWarehouseIds(), strict: true)) {
            throw new AuthorizationException(
                'You are not assigned to the requested virtual warehouse.'
            );
        }
    }

    /**
     * Run $callback with VW access checks disabled, then restore.
     */
    public static function withoutChecks(callable $callback): mixed
    {
        $previous        = static::$bypass;
        static::$bypass  = true;

        try {
            return $callback();
        } finally {
            static::$bypass = $previous;
        }
    }
}
