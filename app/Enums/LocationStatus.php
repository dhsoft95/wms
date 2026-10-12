<?php

namespace App\Enums;

use App\Exceptions\InvalidTransitionException;

enum LocationStatus: string
{
    case Active  = 'active';
    case Blocked = 'blocked';
    case Full    = 'full';

    public function label(): string
    {
        return match($this) {
            self::Active  => 'Active',
            self::Blocked => 'Blocked',
            self::Full    => 'Full',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return match($this) {
            self::Active  => $target === self::Blocked,
            self::Blocked => $target === self::Active,
            self::Full    => false,
        };
    }

    public function transition(self $target): self
    {
        if (! $this->canTransitionTo($target)) {
            throw new InvalidTransitionException($this->value, $target->value);
        }

        return $target;
    }
}
