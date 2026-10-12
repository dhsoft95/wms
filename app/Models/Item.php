<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVirtualWarehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Item extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, BelongsToVirtualWarehouse;

    protected string $virtualWarehouseColumn = 'default_virtual_warehouse_id';

    protected $fillable = [
        'sku',
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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'track_batch'   => 'boolean',
            'track_expiry'  => 'boolean',
            'track_serial'  => 'boolean',
            'is_active'     => 'boolean',
            'length'        => 'decimal:3',
            'width'         => 'decimal:3',
            'height'        => 'decimal:3',
            'weight'        => 'decimal:3',
            'min_stock'     => 'decimal:4',
            'max_stock'     => 'decimal:4',
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

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
