<?php
declare(strict_types=1);
namespace App\Services\Scanning;
use App\Enums\ScanResultType;
use Illuminate\Database\Eloquent\Model;

readonly class ScanResult
{
    public function __construct(
        public ScanResultType $type,
        public ?Model $record = null,
        public array $candidates = [],
        public ?array $gs1Data = null,
        public ?string $error = null,
    ) {}

    public function ok(): bool
    {
        return $this->type !== ScanResultType::Unknown
            && $this->type !== ScanResultType::Ambiguous;
    }
}
