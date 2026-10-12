<?php

namespace App\Actions\Foundation;

use App\Models\Department;

class DeleteDepartmentAction
{
    /**
     * Deactivates the department when it has users or VWs; otherwise deletes it.
     * Returns 'deleted' or 'deactivated'.
     */
    public static function execute(Department $department): string
    {
        if ($department->isInUse()) {
            $department->update(['is_active' => false]);
            return 'deactivated';
        }

        $department->delete();
        return 'deleted';
    }
}
