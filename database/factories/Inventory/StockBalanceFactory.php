<?php

namespace Database\Factories\Inventory;

use App\Enums\Inventory\StockStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Location;
use App\Models\VirtualWarehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBalance>
 */
class StockBalanceFactory extends Factory
{
    protected $model = StockBalance::class;

    public function definition(): array
    {
        $itemId  = Item::factory()->create()->id;
        $locId   = Location::factory()->create()->id;
        $vwId    = VirtualWarehouse::factory()->create()->id;

        return [
            'item_id'              => $itemId,
            'location_id'          => $locId,
            'virtual_warehouse_id' => $vwId,
            'stock_status'         => StockStatus::Available,
            'key_hash'             => hash('sha256', implode('|', [$itemId, '', '', $locId, $vwId, StockStatus::Available->value])),
            'qty_on_hand'          => '10.0000',
            'qty_allocated'        => '0.0000',
        ];
    }
}
