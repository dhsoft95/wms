<?php
namespace Database\Factories;
use App\Enums\UomType;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;
class UnitOfMeasureFactory extends Factory {
    protected $model = UnitOfMeasure::class;
    private static int $n = 1;
    public function definition(): array {
        return [
            'code'      => 'UOM' . (self::$n++),
            'name'      => $this->faker->word(),
            'type'      => UomType::Count->value,
            'is_active' => true,
        ];
    }
}
