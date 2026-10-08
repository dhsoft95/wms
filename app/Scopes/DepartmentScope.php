<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Applied to models with a department_id column.
 * When the authenticated user has the department-user role,
 * every query is automatically filtered to their own department.
 * This is enforced at the Eloquent level — not just in the UI.
 */
class DepartmentScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();

        if ($user->hasRole('department-user') && $user->department_id) {
            $builder->where($model->getTable() . '.department_id', $user->department_id);
        }
    }
}
