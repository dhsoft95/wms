<?php

namespace App\Support\Navigation;

class NavigationBadges
{
    public function get(string $key): ?int
    {
        return match ($key) {
            'approvals' => $this->pendingApprovals(),
            default     => null,
        };
    }

    protected function pendingApprovals(): ?int
    {
        // TODO: replace with real Eloquent query once models exist
        return null;
    }
}
