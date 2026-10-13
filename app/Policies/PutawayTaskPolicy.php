<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PutawayTask;
use App\Models\User;

class PutawayTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('putaway.view');
    }

    public function view(User $user, PutawayTask $task): bool
    {
        return $user->can('putaway.view');
    }

    public function assign(User $user, PutawayTask $task): bool
    {
        return $user->can('putaway.assign') && $task->isAssignable();
    }

    public function execute(User $user, PutawayTask $task): bool
    {
        return $user->can('putaway.execute') && $task->isExecutable();
    }
}
