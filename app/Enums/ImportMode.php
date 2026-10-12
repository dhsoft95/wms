<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportMode: string
{
    case CreateOnly      = 'create_only';
    case CreateOrUpdate  = 'create_or_update';

    public function label(): string
    {
        return match($this) {
            self::CreateOnly     => 'Create Only',
            self::CreateOrUpdate => 'Create or Update',
        };
    }
}
