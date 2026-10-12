<?php

declare(strict_types=1);

namespace App\Data\Inventory;

use App\Enums\Inventory\StockStatus;
use Carbon\Carbon;

readonly class StockLine
{
    public function __construct(
        public int $itemId,
        public int $locationId,
        public int $virtualWarehouseId,
        public StockStatus $stockStatus,
        public string $enteredQty,        // signed decimal string (+inbound, -outbound)
        public int $enteredUomId,
        public ?int $batchId = null,
        public ?string $batchNo = null,   // alternative to batchId: ledger will find-or-create
        public ?Carbon $expiryDate = null,
        public ?int $lpnId = null,
        public ?int $reservationId = null, // if set, reservation is consumed in this posting
        public array $serials = [],        // serial_no strings for track_serial items
    ) {}
}
