<?php

declare(strict_types=1);

namespace App\Data\Ai;

final readonly class AiResult
{
    public function __construct(
        public string $text,
        public int    $inputTokens,
        public int    $outputTokens,
    ) {}
}
