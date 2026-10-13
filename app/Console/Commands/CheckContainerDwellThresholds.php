<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\Inbound\DwellThresholdNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class CheckContainerDwellThresholds extends Command
{
    protected $signature   = 'containers:check-dwell';
    protected $description = 'Notify warehouse managers when containers reach dwell warning or critical thresholds.';

    public function handle(): int
    {
        $warningDays  = Setting::get('container_dwell_warning_days', 7);
        $criticalDays = Setting::get('container_dwell_critical_days', 14);
        $managers     = User::role('warehouse-manager')->get();

        if ($managers->isEmpty()) {
            return self::SUCCESS;
        }

        $activeStatuses = array_column(
            array_filter(ContainerStatus::cases(), fn(ContainerStatus $s) => $s->isActive()),
            'value'
        );

        Container::whereIn('status', $activeStatuses)
            ->whereNotNull('arrived_at')
            ->each(function (Container $container) use ($warningDays, $criticalDays, $managers) {
                $dwellDays = $container->dwellDays();

                foreach (['warning' => $warningDays, 'critical' => $criticalDays] as $threshold => $days) {
                    if ($dwellDays < $days) {
                        continue;
                    }

                    // Only notify once per threshold per container — check notifications table
                    $alreadySent = DB::table('notifications')
                        ->where('type', DwellThresholdNotification::class)
                        ->whereJsonContains('data->container_id', $container->id)
                        ->whereJsonContains('data->threshold', $threshold)
                        ->exists();

                    if (!$alreadySent) {
                        Notification::send($managers, new DwellThresholdNotification($container, $threshold, $dwellDays));
                    }
                }
            });

        return self::SUCCESS;
    }
}
