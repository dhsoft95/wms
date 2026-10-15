<?php

declare(strict_types=1);

namespace App\Listeners\Inbound;

use App\Events\Inbound\ContainerArrived;
use App\Models\User;
use App\Notifications\Inbound\ContainerArrivedNotification;
use Illuminate\Support\Facades\Notification;

final class NotifyContainerArrived
{
    public function handle(ContainerArrived $event): void
    {
        $vwIds = $event->container->expectedLines()->pluck('virtual_warehouse_id')->unique()->filter();

        $vwUsers = $vwIds->isNotEmpty()
            ? User::whereHas('virtualWarehouses', fn($q) => $q->whereIn('virtual_warehouses.id', $vwIds))->get()
            : collect();

        $managers = \Spatie\Permission\Models\Role::where('name', 'warehouse-manager')->exists()
            ? User::role('warehouse-manager')->get()
            : collect();

        $users = $managers->merge($vwUsers)->unique('id');

        Notification::send($users, new ContainerArrivedNotification($event->container));
    }
}
