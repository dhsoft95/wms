<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Enums\Inventory\SerialStatus;
use App\Enums\Inventory\StockStatus;
use App\Models\Item;
use App\Models\Location;
use App\Models\VirtualWarehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SerialNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'serial_no',
        'serial',
        'batch_id',
        'location_id',
        'virtual_warehouse_id',
        'lpn_id',
        'stock_status',
        'status',
        'first_received_at',
        'last_transaction_id',
    ];

    // Allow 'serial' as an alias for 'serial_no' (used by tests and factories)
    public function setSerialAttribute(string $value): void
    {
        $this->attributes['serial_no'] = static::normalize($value);
    }

    public function getSerialAttribute(): ?string
    {
        return $this->attributes['serial_no'] ?? null;
    }

    protected function casts(): array
    {
        return [
            'status'             => SerialStatus::class,
            'stock_status'       => StockStatus::class,
            'first_received_at'  => 'datetime',
        ];
    }

    // ─── Normalization ────────────────────────────────────────────────────────

    public static function normalize(string $serial): string
    {
        return strtoupper(trim($serial));
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function virtualWarehouse(): BelongsTo
    {
        return $this->belongsTo(VirtualWarehouse::class);
    }

    public function lpn(): BelongsTo
    {
        return $this->belongsTo(Lpn::class);
    }

    public function lastTransaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'last_transaction_id');
    }

    public function movements(): BelongsToMany
    {
        return $this->belongsToMany(
            StockMovement::class,
            'stock_movement_serials',
            'serial_number_id',
            'stock_movement_id'
        );
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeInStock($query): mixed
    {
        return $query->where('status', SerialStatus::InStock);
    }

    public function scopeForVirtualWarehouse($query, int $vwId): mixed
    {
        return $query->where('virtual_warehouse_id', $vwId);
    }
}
