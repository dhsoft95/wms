<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Inventory\DisposalStatus;
use App\Models\DisposalRequest;
use App\Models\User;
use App\Services\Inventory\DisposalWorkflow;

class DisposalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('disposals.create');
    }

    public function view(User $user, DisposalRequest $request): bool
    {
        return $user->can('disposals.create');
    }

    public function create(User $user): bool
    {
        return $user->can('disposals.create');
    }

    public function approve(User $user, DisposalRequest $request): bool
    {
        if ($request->status !== DisposalStatus::Submitted) {
            return false;
        }

        return app(DisposalWorkflow::class)->canApprove($request, $user);
    }

    public function reject(User $user, DisposalRequest $request): bool
    {
        return $user->can('disposals.approve')
            && in_array($request->status, [
                DisposalStatus::Submitted,
                DisposalStatus::Approved,
                DisposalStatus::Moved,
            ], true);
    }

    public function execute(User $user, DisposalRequest $request): bool
    {
        return $user->can('disposals.execute')
            && in_array($request->status, [
                DisposalStatus::Approved,
                DisposalStatus::Moved,
            ], true);
    }
}
