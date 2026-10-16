<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternalMovementLine extends Model
{
    protected $fillable = [
        'internal_movement_id',
        'item_id',
        'from_location_id',
        'to_location_id',
        'batch_no',
        'expiry_date',
        'qty',
        'uom_id',
    ];

    protected function casts(): array
    {
        return [
            'qty'         => 'decimal:4',
            'expiry_date' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InternalMovement::class, 'internal_movement_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function serials(): HasMany
    {
        return $this->hasMany(InternalMovementLineSerial::class);
    }
}
