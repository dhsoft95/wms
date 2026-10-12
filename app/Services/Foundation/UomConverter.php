<?php

namespace App\Services\Foundation;

use App\Models\Item;
use App\Models\ItemUom;
use App\Models\UnitOfMeasure;
use InvalidArgumentException;

class UomConverter
{
    /**
     * Convert a quantity from a given UoM to the item's base UoM.
     *
     * @param  Item              $item
     * @param  int|UnitOfMeasure $fromUom  UoM id or model
     * @param  string|float      $qty
     * @return string  decimal(18,4) string
     */
    public static function toBase(Item $item, int|UnitOfMeasure $fromUom, string|float $qty): string
    {
        $uomId = $fromUom instanceof UnitOfMeasure ? $fromUom->id : $fromUom;

        // Base UoM always has factor 1
        if ($uomId === $item->base_uom_id) {
            return number_format((float) $qty, 4, '.', '');
        }

        $factor = ItemUom::where('item_id', $item->id)
            ->where('uom_id', $uomId)
            ->value('factor_to_base');

        if ($factor === null) {
            throw new InvalidArgumentException(
                "UoM ID {$uomId} is not defined for item {$item->sku}."
            );
        }

        return number_format((float) $qty * (float) $factor, 4, '.', '');
    }

    /**
     * Convert a quantity from one UoM to another, going through the base UoM.
     *
     * @param  Item              $item
     * @param  int|UnitOfMeasure $fromUom
     * @param  int|UnitOfMeasure $toUom
     * @param  string|float      $qty
     * @return string  decimal(18,4) string
     */
    public static function convert(
        Item $item,
        int|UnitOfMeasure $fromUom,
        int|UnitOfMeasure $toUom,
        string|float $qty
    ): string {
        $toUomId = $toUom instanceof UnitOfMeasure ? $toUom->id : $toUom;

        // Convert to base first
        $baseQty = (float) static::toBase($item, $fromUom, $qty);

        // If target is base UoM, we're done
        if ($toUomId === $item->base_uom_id) {
            return number_format($baseQty, 4, '.', '');
        }

        $toFactor = ItemUom::where('item_id', $item->id)
            ->where('uom_id', $toUomId)
            ->value('factor_to_base');

        if ($toFactor === null) {
            throw new InvalidArgumentException(
                "Target UoM ID {$toUomId} is not defined for item {$item->sku}."
            );
        }

        return number_format($baseQty / (float) $toFactor, 4, '.', '');
    }
}
