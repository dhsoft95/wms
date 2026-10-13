<?php
namespace Database\Factories;
use App\Enums\GrnSource;
use App\Enums\GrnStatus;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
class GrnFactory extends Factory {
    private static int $n = 1;
    public function definition(): array {
        return [
            'number'       => 'GRN-' . str_pad((string)(self::$n++), 6, '0', STR_PAD_LEFT),
            'source'       => GrnSource::Manual->value,
            'warehouse_id' => Warehouse::factory(),
            'status'       => GrnStatus::Draft->value,
        ];
    }
}
