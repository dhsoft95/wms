<?php
namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;
class WarehouseFactory extends Factory {
    private static int $n = 1;
    public function definition(): array {
        $n = self::$n++;
        return [
            'code'      => 'WH-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'name'      => $this->faker->company() . ' Warehouse',
            'is_active' => true,
        ];
    }
}
