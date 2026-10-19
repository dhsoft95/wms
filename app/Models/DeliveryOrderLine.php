<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Outbound\DoLineStatus;
use App\Models\Inventory\Batch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryOrderLine extends Model
{
    use HasFactory;
    protected $fillable = [
        'delivery_order_id', 'line_no', 'item_id', 'uom_id',
        'requested_qty', 'requested_qty_base', 'requested_batch_id',
        'allocated_qty', 'picked_qty', 'short_qty', 'dispatched_qty',
        'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status'             => DoLineStatus::class,
            'requested_qty'      => 'decimal:4',
            'requested_qty_base' => 'decimal:4',
            'allocated_qty'      => 'decimal:4',
            'picked_qty'         => 'decimal:4',
            'short_qty'          => 'decimal:4',
            'dispatched_qty'     => 'decimal:4',
        ];
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function requestedBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'requested_batch_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(DeliveryOrderAllocation::class);
    }
}
