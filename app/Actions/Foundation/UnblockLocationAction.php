<?php

namespace App\Actions\Foundation;

use App\Enums\LocationStatus;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

class UnblockLocationAction
{
    public static function execute(Location $location): void
    {
        $location->status->transition(LocationStatus::Active); // throws InvalidTransitionException

        DB::transaction(function () use ($location) {
            $location->update([
                'status'         => LocationStatus::Active,
                'blocked_reason' => null,
                'blocked_by'     => null,
                'blocked_at'     => null,
            ]);
        });
    }
}
