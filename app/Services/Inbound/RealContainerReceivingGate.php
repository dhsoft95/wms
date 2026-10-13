<?php

declare(strict_types=1);

namespace App\Services\Inbound;

use App\Contracts\ContainerReceivingGate;
use App\Enums\GrnStatus;
use App\Models\Grn;

final class RealContainerReceivingGate implements ContainerReceivingGate
{
    /** @var GrnStatus[] Statuses that are considered "done" for unload purposes */
    private const DONE_STATUSES = [
        GrnStatus::Received,
        GrnStatus::PutawayInProgress,
        GrnStatus::PutawayComplete,
        GrnStatus::Closed,
    ];

    /**
     * Returns null if the container can be marked Unloaded, or a reason string if blocked.
     */
    public function checkReadyForUnloaded(int $containerId): ?string
    {
        $notDoneCount = Grn::where('container_id', $containerId)
            ->whereNotIn('status', array_column(self::DONE_STATUSES, 'value'))
            ->whereNull('deleted_at')
            ->count();

        if ($notDoneCount > 0) {
            return "Container has {$notDoneCount} GRN(s) that have not been fully received yet.";
        }

        return null;
    }
}
