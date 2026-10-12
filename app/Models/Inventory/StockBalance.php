<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Enums\Inventory\StockStatus;
use App\Models\Item;
use App\Models\Location;
use App\Models\VirtualWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBalance extends Model
{
    const CREATED_AT = null;
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'item_id',
        'batch_id',
        'lpn_id',
        'location_id',
        'virtual_warehouse_id',
        'stock_status',
        'key_hash',
        'qty_on_hand',
        'qty_allocated',
    ];

    protected function casts(): array
    {
        return [
            'stock_status'  => StockStatus::class,
            'qty_on_hand'   => 'decimal:4',
            'qty_allocated' => 'decimal:4',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

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

    public function virtualWarehouse(): BelongsTo
    {
        return $this->belongsTo(VirtualWarehouse::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    // ─── Methods ─────────────────────────────────────────────────────────────

    public function qtyAvailable(): string
    {
        return number_format(
            (float) $this->qty_on_hand - (float) $this->qty_allocated,
            4,
            '.',
            ''
        );
    }
}
