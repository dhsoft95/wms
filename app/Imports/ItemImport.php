<?php

declare(strict_types=1);

namespace App\Imports;

use App\Actions\Foundation\UpdateItemAction;
use App\Enums\UomType;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;

class ItemImport extends BaseImport
{
    /** @var bool Allow creating missing categories on the fly */
    public bool $allowAutoCategory = false;

    public function headers(): array
    {
        return [
            'sku', 'name', 'description', 'category', 'base_uom',
            'length_cm', 'width_cm', 'height_cm', 'weight_kg',
            'track_batch', 'track_expiry', 'track_serial',
            'min_stock', 'max_stock', 'default_vw_code',
            'barcode', 'barcode_2', 'uom_2', 'factor_2', 'uom_3', 'factor_3',
        ];
    }

    public function templateRows(): array
    {
        return [
            [
                'sku' => 'RTR-001', 'name' => 'Cisco Router 100', 'description' => 'Managed router',
                'category' => 'Routers', 'base_uom' => 'PCS',
                'length_cm' => '30', 'width_cm' => '20', 'height_cm' => '5', 'weight_kg' => '1.5',
                'track_batch' => 'no', 'track_expiry' => 'no', 'track_serial' => 'yes',
                'min_stock' => '5', 'max_stock' => '100', 'default_vw_code' => '',
                'barcode' => '1234567890', 'barcode_2' => '', 'uom_2' => 'BOX', 'factor_2' => '10', 'uom_3' => '', 'factor_3' => '',
            ],
        ];
    }

    public function columnInstructions(): array
    {
        return [
            'sku'            => 'Required. Unique SKU code.',
            'name'           => 'Required.',
            'description'    => 'Optional.',
            'category'       => 'Required. Must exist (or enable auto-create category in UI).',
            'base_uom'       => 'Required. Must match a UoM code (e.g. PCS, KG).',
            'track_serial'   => 'yes/no. Requires a count-type base UoM.',
            'track_batch'    => 'yes/no.',
            'track_expiry'   => 'yes/no.',
            'uom_2/factor_2' => 'Optional alternate UoM and conversion factor to base.',
            'uom_3/factor_3' => 'Optional alternate UoM and conversion factor to base.',
        ];
    }

    protected function validateRow(int $rowNumber, array $row): array
    {
        $errors = [];

        if ($this->str($row, 'sku') === '') {
            $errors[] = $this->err('sku', "'sku' is required.");
        }
        if ($this->str($row, 'name') === '') {
            $errors[] = $this->err('name', "'name' is required.");
        }

        $baseUomCode = $this->str($row, 'base_uom');
        $baseUom     = UnitOfMeasure::where('code', $baseUomCode)->first();
        if ($baseUom === null) {
            $errors[] = $this->err('base_uom', "UoM '{$baseUomCode}' not found.");
        }

        $catName = $this->str($row, 'category');
        if ($catName === '') {
            $errors[] = $this->err('category', "'category' is required.");
        } elseif (! $this->allowAutoCategory && ! ItemCategory::where('name', $catName)->exists()) {
            $errors[] = $this->err('category', "Category '{$catName}' not found.");
        }

        $trackSerial = strtolower($this->str($row, 'track_serial'));
        if ($trackSerial === 'yes' && $baseUom && $baseUom->type !== UomType::Count) {
            $errors[] = $this->err('track_serial', "Serial tracking requires a count-type base UoM.");
        }

        if ($this->str($row, 'default_vw_code') !== '' && ! VirtualWarehouse::where('code', $this->str($row, 'default_vw_code'))->exists()) {
            $errors[] = $this->err('default_vw_code', "Virtual warehouse not found.");
        }

        return $errors;
    }

    protected function importRow(array $row): string
    {
        $baseUom   = UnitOfMeasure::where('code', $this->str($row, 'base_uom'))->firstOrFail();
        $catName   = $this->str($row, 'category');
        $category  = $this->allowAutoCategory
            ? ItemCategory::firstOrCreate(['name' => $catName])
            : ItemCategory::where('name', $catName)->firstOrFail();

        $defaultVw = null;
        if ($this->str($row, 'default_vw_code') !== '') {
            $defaultVw = VirtualWarehouse::where('code', $this->str($row, 'default_vw_code'))->first();
        }

        $item  = Item::firstOrNew(['sku' => $this->str($row, 'sku')]);
        $isNew = ! $item->exists;

        if ($this->import->mode->value === 'create_only' && ! $isNew) {
            return 'updated';
        }

        $itemData = [
            'name'                        => $this->str($row, 'name'),
            'description'                 => $this->str($row, 'description') ?: null,
            'category_id'                 => $category->id,
            'base_uom_id'                 => $baseUom->id,
            'length'                      => $this->num($row, 'length_cm'),
            'width'                       => $this->num($row, 'width_cm'),
            'height'                      => $this->num($row, 'height_cm'),
            'weight'                      => $this->num($row, 'weight_kg'),
            'track_batch'                 => $this->bool($row, 'track_batch'),
            'track_expiry'                => $this->bool($row, 'track_expiry'),
            'track_serial'                => $this->bool($row, 'track_serial'),
            'min_stock'                   => $this->num($row, 'min_stock'),
            'max_stock'                   => $this->num($row, 'max_stock'),
            'default_virtual_warehouse_id' => $defaultVw?->id,
            'is_active'                   => true,
        ];

        $uoms = [];
        foreach ([2, 3] as $i) {
            $code   = $this->str($row, "uom_{$i}");
            $factor = $this->str($row, "factor_{$i}");
            if ($code !== '' && $factor !== '') {
                $uom = UnitOfMeasure::where('code', $code)->first();
                if ($uom) {
                    $uoms[] = ['uom_id' => $uom->id, 'factor_to_base' => $factor];
                }
            }
        }

        $barcodes = [];
        foreach (['barcode', 'barcode_2'] as $col) {
            $bc = $this->str($row, $col);
            if ($bc !== '') {
                $barcodes[] = [
                    'barcode'    => $bc,
                    'uom_id'     => $baseUom->id,
                    'type'       => 'ean13',
                    'is_primary' => $col === 'barcode',
                ];
            }
        }

        if ($isNew) {
            $item->fill(array_merge($itemData, ['sku' => $this->str($row, 'sku')]));
            $item->save();
            // Create base UoM row
            $item->uoms()->updateOrCreate(
                ['uom_id' => $baseUom->id],
                ['factor_to_base' => '1.0000']
            );
            foreach ($uoms as $u) {
                $item->uoms()->updateOrCreate(['uom_id' => $u['uom_id']], ['factor_to_base' => $u['factor_to_base']]);
            }
            foreach ($barcodes as $b) {
                $item->barcodes()->create($b);
            }
        } else {
            UpdateItemAction::execute($item, $itemData, $uoms, $barcodes);
        }

        return $isNew ? 'created' : 'updated';
    }

    private function bool(array $row, string $key): bool
    {
        return strtolower($this->str($row, $key)) === 'yes';
    }

    private function num(array $row, string $key): ?string
    {
        $v = $this->str($row, $key);
        return $v !== '' ? $v : null;
    }
}
