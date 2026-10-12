<?php

namespace App\Actions\Foundation;

use App\Enums\UomType;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\ItemUom;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\DB;

class UpdateItemAction
{
    public static function execute(Item $item, array $itemData, array $uoms, array $barcodes): void
    {
        // Reject enabling track_serial on a non-count (weight/volume/length) UoM
        if (array_key_exists('track_serial', $itemData) && (bool) $itemData['track_serial']) {
            $baseUomId = $itemData['base_uom_id'] ?? $item->base_uom_id;
            $baseUom   = UnitOfMeasure::find($baseUomId);
            if ($baseUom && $baseUom->type !== UomType::Count) {
                throw new \DomainException('Serial tracking requires a count-type base unit of measure.');
            }
        }

        if ($item->hasStock()) {
            foreach (['track_batch', 'track_expiry', 'track_serial'] as $flag) {
                if (array_key_exists($flag, $itemData) && (bool) $itemData[$flag] !== (bool) $item->{$flag}) {
                    throw new \DomainException("Cannot change {$flag} for an item with existing stock.");
                }
            }
        }

        DB::transaction(function () use ($item, $itemData, $uoms, $barcodes) {
            $item->update($itemData);

            // Sync UoMs: keep the base UoM row (factor=1), delete non-base, re-add from array
            $item->uoms()->where('uom_id', '!=', $item->base_uom_id)->delete();

            // Ensure base UoM row exists with factor 1
            ItemUom::updateOrCreate(
                ['item_id' => $item->id, 'uom_id' => $item->base_uom_id],
                ['factor_to_base' => '1.0000', 'is_default_purchase' => false, 'is_default_issue' => false]
            );

            foreach ($uoms as $row) {
                if (empty($row['uom_id'])) {
                    continue;
                }
                ItemUom::updateOrCreate(
                    ['item_id' => $item->id, 'uom_id' => $row['uom_id']],
                    [
                        'factor_to_base'      => $row['factor_to_base'],
                        'is_default_purchase' => (bool) ($row['is_default_purchase'] ?? false),
                        'is_default_issue'    => (bool) ($row['is_default_issue'] ?? false),
                    ]
                );
            }

            // Fully replace barcodes
            $item->barcodes()->delete();
            foreach ($barcodes as $row) {
                if (empty($row['barcode'])) {
                    continue;
                }
                ItemBarcode::create([
                    'item_id'    => $item->id,
                    'barcode'    => $row['barcode'],
                    'uom_id'     => $row['uom_id'],
                    'type'       => $row['type'],
                    'is_primary' => (bool) ($row['is_primary'] ?? false),
                ]);
            }
        });
    }
}
