<?php
declare(strict_types=1);
namespace App\Services\Scanning;
use App\Enums\ScanResultType;

readonly class ScanContext
{
    public function __construct(
        public ?ScanResultType $expects = null,
        public ?int $warehouseId = null,
        public ?int $virtualWarehouseId = null,
    ) {}
}
