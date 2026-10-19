<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Global scope restricting queries to items/documents the user is authorised
 * for via item_category_users assignments (category + physical warehouse).
 *
 * Models using this trait MUST implement:
 *   applyCategoryUserScope(Builder $builder, Collection $categoryIds, Collection $warehouseIds): void
 */
trait BelongsToCategoryUser
{
    public static function bootBelongsToCategoryUser(): void
    {
        static::addGlobalScope('category_user', function (Builder $builder) {
            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();

            $unrestrictedRoles = array_merge(
                config('wms.vw_unrestricted_roles', []),
                ['viewer-auditor']
            );

            if ($user->hasAnyRole($unrestrictedRoles)) {
                return;
            }

            $assignments = \App\Models\ItemCategoryUser::where('user_id', $user->id)->get();

            if ($assignments->isEmpty()) {
                return;
            }

            $categoryIds  = $assignments->pluck('item_category_id')->unique()->values();
            $warehouseIds = $assignments->pluck('warehouse_id')->unique()->values();

            static::applyCategoryUserScope($builder, $categoryIds, $warehouseIds);
        });
    }

    abstract public static function applyCategoryUserScope(Builder $builder, Collection $categoryIds, Collection $warehouseIds): void;
}
