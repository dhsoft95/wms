<?php

declare(strict_types=1);

namespace App\Listeners\Inbound;

use App\Events\Inbound\ContainerSealMismatch;
use App\Models\User;
use App\Notifications\Inbound\SealMismatchNotification;
use Illuminate\Support\Facades\Notification;

final class NotifySealMismatch
{
    public function handle(ContainerSealMismatch $event): void
    {
        $managers = User::role('warehouse-manager')->get();
        Notification::send(
            $managers,
            new SealMismatchNotification($event->container, $event->expectedSeal, $event->actualSeal)
        );
    }
}
