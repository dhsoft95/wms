<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Inventory\StockStatusRequestStatus;
use App\Models\StockStatusRequest;
use App\Models\User;
use App\Services\Inventory\StockStatusRequestWorkflow;

class StockStatusRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('obsolete.mark');
    }

    public function view(User $user, StockStatusRequest $request): bool
    {
        return $user->can('obsolete.mark');
    }

    public function create(User $user): bool
    {
        return $user->can('obsolete.mark');
    }

    public function approve(User $user, StockStatusRequest $request): bool
    {
        if ($request->status !== StockStatusRequestStatus::Submitted) {
            return false;
        }

        return app(StockStatusRequestWorkflow::class)->canApprove($request, $user);
    }

    public function reject(User $user, StockStatusRequest $request): bool
    {
        return ($user->can('obsolete.mark') || $user->hasRole('warehouse-manager'))
            && in_array($request->status, [
                StockStatusRequestStatus::Submitted,
            ], true);
    }
}
