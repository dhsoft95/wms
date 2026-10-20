<?php

declare(strict_types=1);

namespace App\Services\Inbound;

use App\Models\Grn;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class GrnQueryService
{
    public function queryFor(User $user): Builder
    {
        $unrestrictedRoles = config('wms.vw_unrestricted_roles', []);

        if ($user->hasAnyRole($unrestrictedRoles)) {
            return Grn::query();
        }

        // Department users see GRNs linked to their department's POs
        if ($user->hasRole('department-user') && $user->department_id) {
            return Grn::whereHas('purchaseOrder', function (Builder $q) use ($user) {
                $q->where('department_id', $user->department_id);
            });
        }

        $vwIds = $user->virtualWarehouses()->pluck('virtual_warehouses.id');

        return Grn::whereHas('lines', function (Builder $q) use ($vwIds) {
            $q->whereIn('virtual_warehouse_id', $vwIds);
        });
    }

    public function canAccess(User $user, Grn $grn): bool
    {
        $unrestrictedRoles = config('wms.vw_unrestricted_roles', []);

        if ($user->hasAnyRole($unrestrictedRoles)) {
            return true;
        }

        $vwIds = $user->virtualWarehouses()->pluck('virtual_warehouses.id');

        return $grn->lines()
            ->whereIn('virtual_warehouse_id', $vwIds)
            ->exists();
    }
}
