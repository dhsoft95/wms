<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Enums\Inventory\StockStatus;
use App\Models\Concerns\BelongsToCategoryUser;
use App\Models\Item;
use App\Models\Location;
use App\Models\VirtualWarehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class StockBalance extends Model
{
    use HasFactory, BelongsToCategoryUser;

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
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'stock_status'  => StockStatus::class,
            'qty_on_hand'   => 'decimal:4',
            'qty_allocated' => 'decimal:4',
            'received_at'   => 'datetime',
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

    public static function applyCategoryUserScope(Builder $builder, Collection $categoryIds, Collection $warehouseIds): void
    {
        $builder->whereHas('location', fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->whereHas('item', fn ($q) => $q->whereIn('category_id', $categoryIds));
    }
}
