<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'code'   => strtoupper($this->faker->unique()->bothify('LOC-##??')),
            'type'   => 'storage',
            'status' => 'active',
            'level'  => 'bin',
        ];
    }

    public function yardSlot(): static
    {
        return $this->state([
            'type'   => 'yard_slot',
            'code'   => strtoupper($this->faker->unique()->bothify('SLOT-??##')),
            'level'  => 'bin',
            'status' => 'active',
        ]);
    }
}
