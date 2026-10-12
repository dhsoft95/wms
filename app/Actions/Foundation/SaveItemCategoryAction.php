<?php

namespace App\Actions\Foundation;

use App\Exceptions\InUseException;
use App\Models\ItemCategory;

class SaveItemCategoryAction
{
    public static function create(array $data): ItemCategory
    {
        return ItemCategory::create([
            'name'      => $data['name'],
            'parent_id' => $data['parent_id'] ?: null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public static function update(ItemCategory $category, array $data): void
    {
        $isDeactivating = isset($data['is_active']) && ! $data['is_active'] && $category->is_active;

        if ($isDeactivating && $category->isInUse()) {
            throw new InUseException('ItemCategory', $category->name);
        }

        $category->update([
            'name'      => $data['name'],
            'parent_id' => $data['parent_id'] ?: null,
            'is_active' => $data['is_active'] ?? $category->is_active,
        ]);
    }

    public static function toggle(ItemCategory $category): string
    {
        $activating = ! $category->is_active;

        if (! $activating && $category->isInUse()) {
            throw new InUseException('ItemCategory', $category->name);
        }

        $category->update(['is_active' => $activating]);

        return $activating ? 'activated' : 'deactivated';
    }
}
