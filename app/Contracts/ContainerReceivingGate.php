<?php

declare(strict_types=1);

namespace App\Contracts;

interface ContainerReceivingGate
{
    /** Returns null if OK to mark as unloaded, or a human-readable reason why it's blocked. */
    public function checkReadyForUnloaded(int $containerId): ?string;
}
