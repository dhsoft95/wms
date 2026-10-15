<?php

namespace Database\Factories;

use App\Models\VirtualWarehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VirtualWarehouse>
 */
class VirtualWarehouseFactory extends Factory
{
    protected $model = VirtualWarehouse::class;

    public function definition(): array
    {
        return [
            'department_id' => \App\Models\Department::firstOrCreate(
                ['code' => 'DEFAULT'],
                ['name' => 'Default Department', 'is_active' => true]
            )->id,
            'code'      => strtoupper($this->faker->unique()->lexify('VW-???')),
            'name'      => $this->faker->words(2, true) . ' Warehouse',
            'is_active' => true,
        ];
    }
}
