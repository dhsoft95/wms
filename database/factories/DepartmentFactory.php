<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    private static int $n = 1;

    public function definition(): array
    {
        return [
            'name'      => $this->faker->words(2, true) . ' Dept',
            'code'      => 'DEPT-' . str_pad((string) (self::$n++), 3, '0', STR_PAD_LEFT),
            'is_active' => true,
        ];
    }
}
