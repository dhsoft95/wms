<?php

declare(strict_types=1);

namespace App\Listeners\Dashboard;

use App\Events\Inventory\StockPosted;
use App\Services\Dashboard\DashboardMetrics;

class ClearDashboardCacheOnStockPosted
{
    public function handle(StockPosted $event): void
    {
        $userId = $event->transaction->posted_by;
        if ($userId) {
            DashboardMetrics::clearForUser($userId);
        }
    }
}
