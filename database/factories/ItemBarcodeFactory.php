<?php
namespace Database\Factories;
use App\Enums\ItemBarcodeType;
use App\Models\Item;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;
class ItemBarcodeFactory extends Factory {
    private static int $n = 1;
    public function definition(): array {
        $uom = UnitOfMeasure::firstOrCreate(
            ['code' => 'PCS'],
            ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
        );
        return [
            'item_id'    => Item::factory(),
            'barcode'    => 'BC-' . str_pad((string)(self::$n++), 8, '0', STR_PAD_LEFT),
            'uom_id'     => $uom->id,
            'type'       => ItemBarcodeType::Internal->value,
            'is_primary' => false,
        ];
    }
}
