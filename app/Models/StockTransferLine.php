<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Inventory\Batch;
use App\Models\Inventory\Lpn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransferLine extends Model
{
    protected $fillable = [
        'stock_transfer_id',
        'item_id',
        'location_id',
        'to_location_id',
        'batch_id',
        'lpn_id',
        'qty',
        'uom_id',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function lpn(): BelongsTo
    {
        return $this->belongsTo(Lpn::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function serials(): HasMany
    {
        return $this->hasMany(StockTransferLineSerial::class);
    }
}
