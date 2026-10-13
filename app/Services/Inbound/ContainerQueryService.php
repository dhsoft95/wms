<?php

declare(strict_types=1);

namespace App\Services\Inbound;

use App\Models\Container;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ContainerQueryService
{
    public function queryFor(User $user): Builder
    {
        $unrestrictedRoles = config('wms.vw_unrestricted_roles', []);

        if ($user->hasAnyRole($unrestrictedRoles)) {
            return Container::query();
        }

        $vwIds = $user->virtualWarehouses()->pluck('virtual_warehouses.id');

        return Container::whereHas('expectedLines', function (Builder $q) use ($vwIds) {
            $q->whereIn('virtual_warehouse_id', $vwIds);
        });
    }

    public function canAccess(User $user, Container $container): bool
    {
        $unrestrictedRoles = config('wms.vw_unrestricted_roles', []);

        if ($user->hasAnyRole($unrestrictedRoles)) {
            return true;
        }

        $vwIds = $user->virtualWarehouses()->pluck('virtual_warehouses.id');

        return $container->expectedLines()
            ->whereIn('virtual_warehouse_id', $vwIds)
            ->exists();
    }
}
