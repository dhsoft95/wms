<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AdjustmentStatus;
use App\Models\StockAdjustment;
use App\Models\User;

class StockAdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('adjustments.view');
    }

    public function view(User $user, StockAdjustment $adj): bool
    {
        return $user->can('adjustments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('adjustments.create');
    }

    public function submit(User $user, StockAdjustment $adj): bool
    {
        return $user->can('adjustments.create')
            && $adj->status === AdjustmentStatus::Draft
            && $adj->created_by === $user->id;
    }

    public function approve(User $user, StockAdjustment $adj): bool
    {
        return $user->can('adjustments.approve')
            && $adj->status === AdjustmentStatus::Submitted;
    }

    public function reject(User $user, StockAdjustment $adj): bool
    {
        return $user->can('adjustments.approve')
            && $adj->status === AdjustmentStatus::Submitted;
    }
}
