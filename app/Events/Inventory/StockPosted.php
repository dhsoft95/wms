<?php

declare(strict_types=1);

namespace App\Events\Inventory;

use App\Models\Inventory\StockTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly StockTransaction $transaction) {}
}
