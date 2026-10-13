<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\User;
use App\Services\Inbound\ContainerQueryService;

class ContainerPolicy
{
    public function __construct(private readonly ContainerQueryService $queryService) {}

    public function viewAny(User $user): bool
    {
        return $user->can('containers.view');
    }

    public function view(User $user, Container $container): bool
    {
        return $user->can('containers.view') && $this->queryService->canAccess($user, $container);
    }

    public function create(User $user): bool
    {
        return $user->can('containers.create');
    }

    public function update(User $user, Container $container): bool
    {
        return $user->can('containers.update') && $container->isEditable();
    }

    public function arrive(User $user, Container $container): bool
    {
        return $user->can('containers.arrive') && $container->status === ContainerStatus::Expected;
    }

    public function park(User $user, Container $container): bool
    {
        return $user->can('containers.park') && $container->status === ContainerStatus::Arrived;
    }

    public function moveSlot(User $user, Container $container): bool
    {
        return $user->can('containers.park') && $container->status === ContainerStatus::Parked;
    }

    public function startUnloading(User $user, Container $container): bool
    {
        return $user->can('containers.unload') && in_array($container->status, [
            ContainerStatus::Arrived,
            ContainerStatus::Parked,
        ], true);
    }

    public function markUnloaded(User $user, Container $container): bool
    {
        return $user->can('containers.unload') && $container->status === ContainerStatus::Unloading;
    }

    public function release(User $user, Container $container): bool
    {
        return $user->can('containers.release') && $container->status === ContainerStatus::Unloaded;
    }

    public function cancel(User $user, Container $container): bool
    {
        return $user->can('containers.cancel') && $container->status === ContainerStatus::Expected;
    }

    public function uploadDocument(User $user, Container $container): bool
    {
        return $user->can('containers.documents') && $this->queryService->canAccess($user, $container);
    }

    public function deleteDocument(User $user, Container $container): bool
    {
        return $user->can('containers.documents.delete') && $this->queryService->canAccess($user, $container);
    }

    public function addDiscrepancy(User $user, Container $container): bool
    {
        return $user->can('containers.discrepancies') && $this->queryService->canAccess($user, $container);
    }

    public function resolveDiscrepancy(User $user, Container $container): bool
    {
        return $user->can('containers.discrepancies.resolve') && $this->queryService->canAccess($user, $container);
    }

    public function overrideNumber(User $user): bool
    {
        return $user->can('containers.override-number');
    }
}
