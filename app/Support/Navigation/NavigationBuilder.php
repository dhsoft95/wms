<?php

namespace App\Support\Navigation;

use Illuminate\Support\Facades\Auth;

class NavigationBuilder
{
    public function __construct(
        protected NavigationBadges $badges,
    ) {}

    public function build(): array
    {
        $groups = [];

        foreach (config('navigation.groups', []) as $group) {
            $user = Auth::user();

            if (!empty($group['can']) && !$user?->can($group['can'])) {
                continue;
            }

            $items = [];
            foreach ($group['items'] as $item) {
                if (isset($item['enabled']) && $item['enabled'] === false) {
                    continue;
                }

                if (!empty($item['can']) && !$user?->can($item['can'])) {
                    continue;
                }

                $isActive = $this->isActive($item['active_routes'] ?? [$item['route']]);

                $items[] = [
                    'key'       => $item['key'],
                    'label'     => $item['label'],
                    'icon'      => $item['icon'],
                    'url'       => route($item['route']),
                    'route'     => $item['route'],
                    'is_active' => $isActive,
                    'badge'     => isset($item['badge']) ? $this->badges->get($item['badge']) : null,
                ];
            }

            if (empty($items)) {
                continue;
            }

            $groups[] = [
                'key'        => $group['key'],
                'label'      => $group['label'],
                'items'      => $items,
                'has_active' => collect($items)->contains('is_active', true),
            ];
        }

        return $groups;
    }

    protected function isActive(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }
        return false;
    }
}
