<?php

declare(strict_types=1);

namespace App\Enums;

use App\Exceptions\InvalidTransitionException;

enum CycleCountStatus: string
{
    case Open           = 'open';
    case Counting       = 'counting';
    case VarianceReview = 'variance_review';
    case Posted         = 'posted';

    public function canTransitionTo(self $new): bool
    {
        return match ($this) {
            self::Open           => in_array($new, [self::Counting], true),
            self::Counting       => in_array($new, [self::VarianceReview], true),
            self::VarianceReview => in_array($new, [self::Posted, self::Counting], true),
            default              => false,
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
            self::Open           => 'Open',
            self::Counting       => 'Counting',
            self::VarianceReview => 'Variance Review',
            self::Posted         => 'Posted',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Open           => 'badge-brand',
            self::Counting       => 'badge-amber',
            self::VarianceReview => 'badge-amber',
            self::Posted         => 'badge-neutral',
        };
    }

    public function isLocked(): bool
    {
        return in_array($this, [self::Open, self::Counting, self::VarianceReview], true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Posted;
    }
}
