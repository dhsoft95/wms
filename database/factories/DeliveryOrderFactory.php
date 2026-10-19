<?php

namespace Database\Factories;

use App\Enums\Outbound\DeliveryOrderStatus;
use App\Models\DeliveryOrder;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryOrder>
 */
class DeliveryOrderFactory extends Factory
{
    protected $model = DeliveryOrder::class;

    private static int $n = 1;

    public function definition(): array
    {
        return [
            'number'               => 'DO-' . str_pad((string) (self::$n++), 6, '0', STR_PAD_LEFT),
            'virtual_warehouse_id' => VirtualWarehouse::factory(),
            'warehouse_id'         => Warehouse::factory(),
            'status'               => DeliveryOrderStatus::Draft,
            'requested_by'         => User::factory(),
        ];
    }
}
