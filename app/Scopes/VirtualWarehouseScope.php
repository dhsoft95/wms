<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Applied via BelongsToVirtualWarehouse to any model with a virtual_warehouse_id column.
 * Skipped for console/queue (no authenticated user) and for unrestricted roles.
 * A restricted user with zero assigned VWs sees an empty result set.
 */
class VirtualWarehouseScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();

        if ($user->canAccessAllVirtualWarehouses()) {
            return;
        }

        $ids = $user->accessibleVirtualWarehouseIds();

        if (empty($ids)) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->whereIn($model->getTable() . '.' . (method_exists($model, 'getVirtualWarehouseColumn') ? $model->getVirtualWarehouseColumn() : 'virtual_warehouse_id'), $ids);
    }
}
