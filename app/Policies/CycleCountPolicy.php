<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CycleCountStatus;
use App\Models\CycleCount;
use App\Models\User;

class CycleCountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('counts.view');
    }

    public function view(User $user, CycleCount $count): bool
    {
        return $user->can('counts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('counts.create');
    }

    public function recordCount(User $user, CycleCount $count): bool
    {
        return $user->can('counts.count')
            && in_array($count->status, [CycleCountStatus::Open, CycleCountStatus::Counting], true);
    }

    public function approve(User $user, CycleCount $count): bool
    {
        return $user->can('counts.approve')
            && $count->status === CycleCountStatus::VarianceReview;
    }
}
