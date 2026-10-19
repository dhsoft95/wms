<?php

namespace Database\Factories;

use App\Enums\Outbound\DoLineStatus;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderLine;
use App\Models\Item;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryOrderLine>
 */
class DeliveryOrderLineFactory extends Factory
{
    protected $model = DeliveryOrderLine::class;

    private static int $lineNo = 1;

    public function definition(): array
    {
        return [
            'delivery_order_id'  => DeliveryOrder::factory(),
            'line_no'            => self::$lineNo++,
            'item_id'            => Item::factory(),
            'uom_id'             => UnitOfMeasure::factory(),
            'requested_qty'      => '1.0000',
            'requested_qty_base' => '1.0000',
            'status'             => DoLineStatus::Pending,
        ];
    }
}
