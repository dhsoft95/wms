<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Batch;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    protected $model = Batch::class;

    private static int $n = 1;

    public function definition(): array
    {
        return [
            'item_id'  => Item::factory(),
            'batch_no' => 'BATCH-' . str_pad((string) (self::$n++), 6, '0', STR_PAD_LEFT),
        ];
    }
}
