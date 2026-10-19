<?php

namespace App\Actions\Foundation;

use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\ItemUom;
use Illuminate\Support\Facades\DB;

class CreateItemAction
{
    public static function execute(array $itemData, array $uoms, array $barcodes = [], int $baseUomId = 0): Item
    {
        return DB::transaction(function () use ($itemData, $uoms, $barcodes, $baseUomId) {
            $item = Item::create($itemData);

            // Always ensure base UoM is recorded with factor 1
            $baseAlreadyAdded = collect($uoms)
                ->contains(fn ($row) => (string) $row['uom_id'] === (string) $baseUomId);

            if (! $baseAlreadyAdded && $baseUomId) {
                ItemUom::create([
                    'item_id'             => $item->id,
                    'uom_id'              => $baseUomId,
                    'factor_to_base'      => '1.0000',
                    'is_default_purchase' => false,
                    'is_default_issue'    => false,
                ]);
            }

            foreach ($uoms as $row) {
                if (empty($row['uom_id'])) {
                    continue;
                }
                ItemUom::create([
                    'item_id'             => $item->id,
                    'uom_id'              => $row['uom_id'],
                    'factor_to_base'      => $row['factor_to_base'],
                    'is_default_purchase' => (bool) ($row['is_default_purchase'] ?? false),
                    'is_default_issue'    => (bool) ($row['is_default_issue'] ?? false),
                ]);
            }

            // Auto-generate one internal Code128 barcode from the SKU.
            // If the SKU value is already used as a barcode on another item,
            // append -01, -02, etc. until the value is unique.
            $barcodeValue = $item->sku;
            $suffix       = 0;
            while (ItemBarcode::where('barcode', $barcodeValue)
                ->where('item_id', '!=', $item->id)
                ->exists()
            ) {
                $suffix++;
                $barcodeValue = $item->sku . '-' . str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
            }

            ItemBarcode::create([
                'item_id'    => $item->id,
                'barcode'    => $barcodeValue,
                'uom_id'     => $item->base_uom_id,
                'type'       => 'internal',
                'is_primary' => true,
            ]);

            return $item;
        });
    }
}
