<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderLine>
 */
class PurchaseOrderLineFactory extends Factory
{
    protected $model = PurchaseOrderLine::class;

    private static int $lineNo = 1;

    public function definition(): array
    {
        $uom = UnitOfMeasure::firstOrCreate(
            ['code' => 'PCS'],
            ['name' => 'Pieces', 'type' => 'count', 'is_active' => true]
        );

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'line_no'           => self::$lineNo++,
            'item_id'           => Item::factory(),
            'uom_id'            => $uom->id,
            'qty'               => $this->faker->randomFloat(2, 1, 100),
            'qty_base'          => $this->faker->randomFloat(2, 1, 100),
            'batch_no'          => null,
            'expiry_date'       => null,
            'notes'             => null,
        ];
    }
}
