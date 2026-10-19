<?php

declare(strict_types=1);

namespace App\Enums\Inbound;

enum PoIssueStatus: string
{
    case Open     = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match($this) {
            self::Open     => 'Open',
            self::Resolved => 'Resolved',
        };
    }
}
