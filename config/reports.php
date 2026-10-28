<?php

declare(strict_types=1);

return [
    /*
     * Filesystem disk for generated report files.
     * Set REPORT_DISK=s3 (or any configured disk) in .env for cloud storage.
     * Defaults to 'local' (storage/app/private).
     */
    'disk' => env('REPORT_DISK', 'local'),

    'Stock' => [
        \App\Reports\Stock\StockOnHandReport::class,
        \App\Reports\Stock\StockHoldingReport::class,
        \App\Reports\Stock\StockAgingReport::class,
        \App\Reports\Stock\StockInTransitReport::class,
    ],
    'Orders' => [
        \App\Reports\Orders\PendingOrdersReport::class,
        \App\Reports\Orders\DeliveredPendingGrnReport::class,
        \App\Reports\Orders\PlannedOrdersReport::class,
    ],
    'Activity' => [
        \App\Reports\Activity\StockMovementReport::class,
        \App\Reports\Activity\ReceivedDispatchedReport::class,
    ],
    'Control' => [
        \App\Reports\Control\StockCountReport::class,
        \App\Reports\Control\StockAdjustmentReport::class,
        \App\Reports\Control\AssetDisposalReport::class,
    ],
];
