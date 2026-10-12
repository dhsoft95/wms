<?php

namespace App\Models\Concerns;

use App\Models\VirtualWarehouse;
use App\Scopes\VirtualWarehouseScope;
use App\Support\VirtualWarehouseAccess;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Add to any model that has a virtual_warehouse_id column.
 *
 * What it does:
 *  - Registers VirtualWarehouseScope so restricted users only see their VWs.
 *  - On creating: auto-fills virtual_warehouse_id from the user's single VW when
 *    none is provided; rejects a VW the user is not assigned to.
 *  - Provides virtualWarehouse() relation and scopeForVirtualWarehouse().
 */
trait BelongsToVirtualWarehouse
{
    public function getVirtualWarehouseColumn(): string
    {
        return property_exists($this, 'virtualWarehouseColumn')
            ? $this->virtualWarehouseColumn
            : 'virtual_warehouse_id';
    }

    public static function bootBelongsToVirtualWarehouse(): void
    {
        static::addGlobalScope(new VirtualWarehouseScope());

        static::creating(function (self $model): void {
            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();

            if ($user->canAccessAllVirtualWarehouses()) {
                return;
            }

            if (empty($model->{$model->getVirtualWarehouseColumn()})) {
                $ids = $user->accessibleVirtualWarehouseIds();
                if (count($ids) === 1) {
                    $model->{$model->getVirtualWarehouseColumn()} = $ids[0];
                }
                return;
            }

            VirtualWarehouseAccess::ensure($user, (int) $model->{$model->getVirtualWarehouseColumn()});
        });
    }

    public function virtualWarehouse(): BelongsTo
    {
        return $this->belongsTo(VirtualWarehouse::class);
    }

    public function scopeForVirtualWarehouse($query, int $vwId): mixed
    {
        return $query->where($this->getTable() . '.' . $this->getVirtualWarehouseColumn(), $vwId);
    }
}
