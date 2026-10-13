<?php

declare(strict_types=1);

namespace App\Services\Inbound;

use App\Contracts\ContainerReceivingGate;

final class DefaultContainerReceivingGate implements ContainerReceivingGate
{
    public function checkReadyForUnloaded(int $containerId): ?string
    {
        return 'Marking a container as unloaded requires the Receiving module (Module 4). It is not yet available.';
    }
}
