<?php

declare(strict_types=1);

namespace App\Data\Inventory;

readonly class StockReference
{
    public function __construct(
        public ?string $referenceType = null,
        public ?int $referenceId = null,
        public ?string $referenceNo = null,
    ) {}
}
