<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Enums\Inventory\StockStatus;
use App\Models\Concerns\BelongsToVirtualWarehouse;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StockMovement extends Model
{
    use BelongsToVirtualWarehouse;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    public $timestamps = false;

    protected $fillable = [
        'stock_transaction_id',
        'item_id',
        'batch_id',
        'lpn_id',
        'location_id',
        'virtual_warehouse_id',
        'stock_status',
        'qty',
        'entered_qty',
        'entered_uom_id',
    ];

    protected function casts(): array
    {
        return [
            'stock_status' => StockStatus::class,
            'qty'          => 'decimal:4',
            'entered_qty'  => 'decimal:4',
        ];
    }

    // ─── Immutability ─────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (): never {
            throw new \RuntimeException('StockTransaction records are immutable.');
        });

        static::deleting(function (): never {
            throw new \RuntimeException('StockTransaction records are immutable.');
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'stock_transaction_id');
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

    public function enteredUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'entered_uom_id');
    }

    public function serials(): BelongsToMany
    {
        return $this->belongsToMany(
            SerialNumber::class,
            'stock_movement_serials',
            'stock_movement_id',
            'serial_number_id'
        );
    }
}
