<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermissionTo('departments.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermissionTo('departments.manage');
    }

    public function update(User $actor, Department $department): bool
    {
        return $actor->hasPermissionTo('departments.manage');
    }

    public function delete(User $actor, Department $department): bool
    {
        if (! $actor->hasPermissionTo('departments.manage')) {
            return false;
        }
        // Cannot delete a department with assigned users — deactivate instead
        return $department->users()->whereNull('deleted_at')->count() === 0;
    }
}
