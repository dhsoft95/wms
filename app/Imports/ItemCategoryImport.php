<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\ItemCategory;

class ItemCategoryImport extends BaseImport
{
    public function headers(): array
    {
        return ['name', 'parent_name'];
    }

    public function templateRows(): array
    {
        return [
            ['name' => 'Electronics', 'parent_name' => ''],
            ['name' => 'Routers',     'parent_name' => 'Electronics'],
        ];
    }

    public function columnInstructions(): array
    {
        return [
            'name'        => 'Required. Category name (unique).',
            'parent_name' => 'Optional. Name of the parent category. Must exist or appear earlier in this file.',
        ];
    }

    protected function validateRow(int $rowNumber, array $row): array
    {
        $errors = [];
        if ($this->str($row, 'name') === '') {
            $errors[] = $this->err('name', "'name' is required.");
        }
        return $errors;
    }

    protected function importRow(array $row): string
    {
        $parentName = $this->str($row, 'parent_name');
        $parentId   = null;

        if ($parentName !== '') {
            $parent = ItemCategory::where('name', $parentName)->first();
            if ($parent === null) {
                // Try to find it already created in this run
                $parent = ItemCategory::where('name', $parentName)->first();
            }
            $parentId = $parent?->id;
        }

        $cat   = ItemCategory::firstOrNew(['name' => $this->str($row, 'name')]);
        $isNew = ! $cat->exists;

        if ($this->import->mode->value === 'create_only' && ! $isNew) {
            return 'updated';
        }

        $cat->parent_id = $parentId;
        $cat->save();

        return $isNew ? 'created' : 'updated';
    }
}
