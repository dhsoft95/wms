<?php
namespace Database\Factories;
use App\Enums\ReasonCodeAppliesTo;
use App\Models\ReasonCode;
use Illuminate\Database\Eloquent\Factories\Factory;
class ReasonCodeFactory extends Factory {
    protected $model = ReasonCode::class;
    private static int $n = 1;
    public function definition(): array {
        return [
            'code'       => 'RC-' . str_pad((string)(self::$n++), 4, '0', STR_PAD_LEFT),
            'name'       => $this->faker->words(3, true),
            'applies_to' => ReasonCodeAppliesTo::Variance->value,
            'is_active'  => true,
        ];
    }
}
