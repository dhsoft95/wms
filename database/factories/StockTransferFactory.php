<?php

namespace Database\Factories;

use App\Enums\TransferStatus;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\VirtualWarehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    private static int $n = 1;

    public function definition(): array
    {
        return [
            'number'                    => 'TR-' . date('Y') . '-' . str_pad((string) (self::$n++), 4, '0', STR_PAD_LEFT),
            'from_virtual_warehouse_id' => VirtualWarehouse::factory(),
            'to_virtual_warehouse_id'   => VirtualWarehouse::factory(),
            'created_by'                => User::factory(),
            'status'                    => TransferStatus::Draft,
            'notes'                     => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(['status' => TransferStatus::Submitted]);
    }

    public function approved(): static
    {
        return $this->state(['status' => TransferStatus::Approved]);
    }
}
