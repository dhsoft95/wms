<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCategoryUser;
use App\Models\Concerns\BelongsToVirtualWarehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Item extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, BelongsToVirtualWarehouse, BelongsToCategoryUser;

    protected string $virtualWarehouseColumn = 'default_virtual_warehouse_id';

    protected $fillable = [
        'sku',
        'part_number',
        'name',
        'description',
        'category_id',
        'base_uom_id',
        'length',
        'width',
        'height',
        'weight',
        'track_batch',
        'track_expiry',
        'track_serial',
        'min_stock',
        'max_stock',
        'default_virtual_warehouse_id',
        'preferred_zone_id',
        'is_active',
        'allocation_rules',
        'variance_threshold_pct',
        'variance_threshold_qty',
        'dispatch_requires_approval',
        'dispatch_approval_qty',
        'adjustment_approval',
    ];

    protected function casts(): array
    {
        return [
            'track_batch'                => 'boolean',
            'track_expiry'               => 'boolean',
            'track_serial'               => 'boolean',
            'is_active'                  => 'boolean',
            'length'                     => 'decimal:3',
            'width'                      => 'decimal:3',
            'height'                     => 'decimal:3',
            'weight'                     => 'decimal:3',
            'min_stock'                  => 'decimal:4',
            'max_stock'                  => 'decimal:4',
            'allocation_rules'           => 'array',
            'variance_threshold_pct'     => 'decimal:2',
            'variance_threshold_qty'     => 'decimal:4',
            'dispatch_requires_approval' => 'boolean',
            'dispatch_approval_qty'      => 'decimal:4',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class);
    }

    public function baseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_uom_id');
    }

    public function uoms(): HasMany
    {
        return $this->hasMany(ItemUom::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ItemBarcode::class);
    }

    public function defaultVirtualWarehouse(): BelongsTo
    {
        return $this->belongsTo(VirtualWarehouse::class, 'default_virtual_warehouse_id');
    }

    public function preferredZone(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'preferred_zone_id');
    }

    // ─── Methods ──────────────────────────────────────────────────────────────

    public function hasTransactions(): bool
    {
        return \App\Models\Inventory\StockMovement::where('item_id', $this->id)->exists();
    }

    public function hasStock(): bool
    {
        return \App\Models\Inventory\StockBalance::where('item_id', $this->id)
            ->where('qty_on_hand', '>', 0)->exists();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    // ─── Category user scope ─────────────────────────────────────────────────

    public static function applyCategoryUserScope(Builder $builder, Collection $categoryIds, Collection $warehouseIds): void
    {
        // Items are not tied to a warehouse; filter by category only.
        // Items with no category are invisible to users with category assignments.
        $builder->whereIn('category_id', $categoryIds);
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
