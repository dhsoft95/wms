<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\Ai\AiResult;

interface AiClient
{
    public function summarise(string $systemInstruction, string $userContent): AiResult;
}
