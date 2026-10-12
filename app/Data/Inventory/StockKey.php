<?php

declare(strict_types=1);

namespace App\Data\Inventory;

use App\Enums\Inventory\StockStatus;

readonly class StockKey
{
    public function __construct(
        public int $itemId,
        public ?int $batchId,
        public ?int $lpnId,
        public int $locationId,
        public int $virtualWarehouseId,
        public StockStatus $stockStatus,
    ) {}

    // SHA-256 hash of the key for the key_hash column in stock_balances.
    // NULLs replaced with 0 so the hash is deterministic.
    public function hash(): string
    {
        return hash('sha256', implode('|', [
            $this->itemId,
            $this->batchId ?? 0,
            $this->lpnId ?? 0,
            $this->locationId,
            $this->virtualWarehouseId,
            $this->stockStatus->value,
        ]));
    }
}
