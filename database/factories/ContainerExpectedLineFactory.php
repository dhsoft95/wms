<?php

namespace Database\Factories;

use App\Models\ContainerExpectedLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContainerExpectedLine>
 */
class ContainerExpectedLineFactory extends Factory
{
    protected $model = ContainerExpectedLine::class;

    public function definition(): array
    {
        return [
            'line_no'              => $this->faker->numberBetween(1, 999),
            'qty'                  => $this->faker->randomFloat(4, 1, 1000),
            'virtual_warehouse_id' => null,
        ];
    }
}
