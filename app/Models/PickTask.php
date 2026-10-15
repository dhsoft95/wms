<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Outbound\PickTaskStatus;
use App\Models\Inventory\Batch;
use App\Models\Inventory\Lpn;
use App\Models\Inventory\SerialNumber;
use App\Models\Inventory\StockTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PickTask extends Model
{
    protected $fillable = [
        'pick_list_id', 'delivery_order_allocation_id', 'sequence',
        'location_id', 'item_id', 'batch_id', 'lpn_id',
        'qty_to_pick', 'qty_picked', 'qty_short', 'status',
        'picked_by', 'picked_at', 'stock_transaction_id', 'short_reason_code_id',
    ];

    protected function casts(): array
    {
        return [
            'status'      => PickTaskStatus::class,
            'qty_to_pick' => 'decimal:4',
            'qty_picked'  => 'decimal:4',
            'qty_short'   => 'decimal:4',
            'picked_at'   => 'datetime',
        ];
    }

    public function pickList(): BelongsTo
    {
        return $this->belongsTo(PickList::class);
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrderAllocation::class, 'delivery_order_allocation_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
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

    public function pickedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_by');
    }

    public function stockTransaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class);
    }

    public function shortReasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'short_reason_code_id');
    }

    public function serials(): BelongsToMany
    {
        return $this->belongsToMany(SerialNumber::class, 'pick_task_serials');
    }
}
