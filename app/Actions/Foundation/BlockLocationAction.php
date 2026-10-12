<?php

namespace App\Actions\Foundation;

use App\Enums\LocationStatus;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

class BlockLocationAction
{
    public static function execute(Location $location, string $reason, int $blockedBy): void
    {
        $location->status->transition(LocationStatus::Blocked); // throws InvalidTransitionException

        DB::transaction(function () use ($location, $reason, $blockedBy) {
            $location->update([
                'status'         => LocationStatus::Blocked,
                'blocked_reason' => $reason,
                'blocked_by'     => $blockedBy,
                'blocked_at'     => now(),
            ]);
        });
    }
}
