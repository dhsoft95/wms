<?php

declare(strict_types=1);

namespace App\Data\Inventory;

use App\Enums\Inventory\MovementType;

readonly class StockPosting
{
    /**
     * @param StockLine[] $lines
     */
    public function __construct(
        public MovementType $type,
        public StockReference $reference,
        public array $lines,
        public ?string $idempotencyKey = null,
        public ?string $notes = null,
        public string $device = 'desktop',
    ) {}
}
