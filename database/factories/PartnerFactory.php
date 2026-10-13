<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        return [
            'code'       => strtoupper($this->faker->unique()->lexify('PTR-???')),
            'name'       => $this->faker->company(),
            'type'       => ['supplier'],
            'is_active'  => true,
        ];
    }
}
