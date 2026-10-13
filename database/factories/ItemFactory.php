<?php
namespace Database\Factories;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;
class ItemFactory extends Factory {
    private static int $n = 1;
    public function definition(): array {
        $uom = UnitOfMeasure::firstOrCreate(
            ['code' => 'PCS'],
            ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
        );
        return [
            'sku'         => 'SKU-' . str_pad((string)(self::$n++), 6, '0', STR_PAD_LEFT),
            'name'        => $this->faker->words(3, true),
            'base_uom_id' => $uom->id,
            'is_active'   => true,
        ];
    }
}
