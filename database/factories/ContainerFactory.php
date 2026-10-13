<?php

namespace Database\Factories;

use App\Enums\ContainerSizeType;
use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Services\Foundation\ISO6346Validator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Container>
 */
class ContainerFactory extends Factory
{
    protected $model = Container::class;

    public function definition(): array
    {
        // Generate a valid ISO 6346 container number
        $owners = ['MSCU', 'CMAU', 'TGHU', 'HLXU', 'GESU'];
        $owner  = $this->faker->randomElement($owners);
        $digits = str_pad((string) $this->faker->numberBetween(0, 999999), 6, '0', STR_PAD_LEFT);
        $prefix = $owner . $digits;
        $check  = ISO6346Validator::checkDigit($prefix);

        return [
            'number'       => 'CTR-' . date('Y') . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'container_no' => $prefix . $check,
            'size_type'    => ContainerSizeType::FortyGP->value,
            'status'       => ContainerStatus::Expected->value,
        ];
    }

    public function expected(): static
    {
        return $this->state(['status' => ContainerStatus::Expected->value]);
    }

    public function arrived(): static
    {
        return $this->state([
            'status'     => ContainerStatus::Arrived->value,
            'arrived_at' => now(),
        ]);
    }

    public function parked(): static
    {
        return $this->state([
            'status'     => ContainerStatus::Parked->value,
            'arrived_at' => now()->subDays(2),
        ]);
    }

    public function unloading(): static
    {
        return $this->state([
            'status'                => ContainerStatus::Unloading->value,
            'arrived_at'            => now()->subDays(3),
            'unloading_started_at'  => now()->subDay(),
        ]);
    }

    public function released(): static
    {
        return $this->state([
            'status'      => ContainerStatus::Released->value,
            'arrived_at'  => now()->subDays(10),
            'released_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => ContainerStatus::Cancelled->value]);
    }
}
