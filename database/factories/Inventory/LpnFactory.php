<?php
namespace Database\Factories\Inventory;
use App\Enums\Inventory\LpnStatus;
use App\Models\Inventory\Lpn;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
class LpnFactory extends Factory {
    protected $model = Lpn::class;
    private static int $n = 1;
    public function definition(): array {
        return [
            'code'        => 'LPN-' . str_pad((string)(self::$n++), 6, '0', STR_PAD_LEFT),
            'status'      => LpnStatus::Active->value,
            'warehouse_id'=> Warehouse::factory(),
            'print_count' => 0,
        ];
    }
}
