<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportStatus: string
{
    case Uploaded          = 'uploaded';
    case Validating        = 'validating';
    case Validated         = 'validated';
    case FailedValidation  = 'failed_validation';
    case Importing         = 'importing';
    case Completed         = 'completed';
    case Failed            = 'failed';

    public function label(): string
    {
        return match($this) {
            self::Uploaded         => 'Uploaded',
            self::Validating       => 'Validating…',
            self::Validated        => 'Ready to Import',
            self::FailedValidation => 'Validation Failed',
            self::Importing        => 'Importing…',
            self::Completed        => 'Completed',
            self::Failed           => 'Failed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Uploaded         => 'slate',
            self::Validating       => 'blue',
            self::Validated        => 'green',
            self::FailedValidation => 'red',
            self::Importing        => 'blue',
            self::Completed        => 'green',
            self::Failed           => 'red',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::FailedValidation], true);
    }

    public function canConfirm(): bool
    {
        return $this === self::Validated;
    }
}
