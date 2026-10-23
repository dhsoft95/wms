<?php

declare(strict_types=1);

return [
    'Stock' => [
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
        \App\Reports\Activity\ReceivedDispatchedReport::class,
    ],
    'Control' => [
        \App\Reports\Control\StockCountReport::class,
        \App\Reports\Control\AssetDisposalReport::class,
    ],
];
