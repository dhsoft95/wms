<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContainerDocument;
use App\Models\User;
use App\Services\Inbound\ContainerQueryService;

class ContainerDocumentPolicy
{
    public function view(User $user, ContainerDocument $document): bool
    {
        return $user->can('containers.view') &&
               app(ContainerQueryService::class)->canAccess($user, $document->container);
    }

    public function delete(User $user, ContainerDocument $document): bool
    {
        return $user->can('containers.documents.delete') &&
               app(ContainerQueryService::class)->canAccess($user, $document->container);
    }
}
