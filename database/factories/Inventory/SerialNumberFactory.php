<?php
namespace Database\Factories\Inventory;
use App\Enums\Inventory\SerialStatus;
use App\Enums\Inventory\StockStatus;
use App\Models\Inventory\SerialNumber;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;
class SerialNumberFactory extends Factory {
    protected $model = SerialNumber::class;
    private static int $n = 1;
    public function definition(): array {
        return [
            'serial_no' => 'SN-' . str_pad((string)(self::$n++), 8, '0', STR_PAD_LEFT),
            'item_id'   => Item::factory(),
            'status'    => SerialStatus::InStock->value,
        ];
    }
}
