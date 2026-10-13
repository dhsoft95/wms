<?php

declare(strict_types=1);

namespace App\Listeners\Inbound;

use App\Events\Inbound\GrnConfirmed;
use App\Models\User;

final class NotifyGrnConfirmed
{
    public function handle(GrnConfirmed $event): void
    {
        $grn = $event->grn;

        // Notify managers and the receiver
        $notifyUsers = User::role(['warehouse-manager', 'system-admin'])->get();

        foreach ($notifyUsers as $user) {
            $user->notify(new \App\Notifications\GrnConfirmedNotification($grn));
        }
    }
}
