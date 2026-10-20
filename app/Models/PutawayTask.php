<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PutawayTaskStatus;
use App\Models\Inventory\Batch;
use App\Models\Inventory\Lpn;
use App\Models\Inventory\StockTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PutawayTask extends Model
{
    protected $table = 'putaway_tasks';

    protected $fillable = [
        'grn_id',
        'lpn_id',
        'item_id',
        'batch_id',
        'virtual_warehouse_id',
        'qty',
        'from_location_id',
        'suggested_location_id',
        'actual_location_id',
        'status',
        'assigned_to',
        'completed_by',
        'completed_at',
        'stock_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'qty'          => 'decimal:4',
            'status'       => PutawayTaskStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function grn(): BelongsTo
    {
        return $this->belongsTo(Grn::class);
    }

    public function lpn(): BelongsTo
    {
        return $this->belongsTo(Lpn::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function virtualWarehouse(): BelongsTo
    {
        return $this->belongsTo(VirtualWarehouse::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function suggestedLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'suggested_location_id');
    }

    public function actualLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'actual_location_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function stockTransaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(PutawayTaskSplit::class);
    }

    // ─── Business Methods ─────────────────────────────────────────────────────

    public function isAssignable(): bool
    {
        return $this->status === PutawayTaskStatus::Open;
    }

    public function isExecutable(): bool
    {
        return in_array($this->status, [PutawayTaskStatus::Assigned, PutawayTaskStatus::InProgress], true);
    }
}
