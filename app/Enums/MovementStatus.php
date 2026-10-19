<?php

declare(strict_types=1);

namespace App\Enums;

use App\Exceptions\InvalidTransitionException;

enum MovementStatus: string
{
    case Draft     = 'draft';
    case Submitted = 'submitted';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $new): bool
    {
        return match ($this) {
            self::Draft     => in_array($new, [self::Submitted, self::Completed, self::Cancelled], true),
            self::Submitted => in_array($new, [self::Completed, self::Cancelled], true),
            default         => false,
        };
    }

    public function transitionTo(self $new): self
    {
        if (! $this->canTransitionTo($new)) {
            throw new InvalidTransitionException($this->value, $new->value);
        }

        return $new;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Draft',
            self::Submitted => 'Submitted',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft     => 'badge-slate',
            self::Submitted => 'badge-amber',
            self::Completed => 'badge-green',
            self::Cancelled => 'badge-red',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}
