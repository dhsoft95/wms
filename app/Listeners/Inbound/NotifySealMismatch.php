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
        $managers = \Spatie\Permission\Models\Role::where('name', 'warehouse-manager')->exists()
            ? User::role('warehouse-manager')->get()
            : collect();
        Notification::send(
            $managers,
            new SealMismatchNotification($event->container, $event->expectedSeal, $event->actualSeal)
        );
    }
}
