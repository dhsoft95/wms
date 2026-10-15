<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Outbound\AllocationStatus;
use App\Models\Inventory\Batch;
use App\Models\Inventory\Lpn;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockReservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryOrderAllocation extends Model
{
    protected $fillable = [
        'delivery_order_line_id', 'stock_balance_id', 'stock_reservation_id',
        'item_id', 'batch_id', 'lpn_id', 'location_id', 'qty', 'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => AllocationStatus::class,
            'qty'    => 'decimal:4',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrderLine::class, 'delivery_order_line_id');
    }

    public function stockBalance(): BelongsTo
    {
        return $this->belongsTo(StockBalance::class);
    }

    public function stockReservation(): BelongsTo
    {
        return $this->belongsTo(StockReservation::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function lpn(): BelongsTo
    {
        return $this->belongsTo(Lpn::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function pickTask(): HasOne
    {
        return $this->hasOne(PickTask::class, 'delivery_order_allocation_id');
    }
}
