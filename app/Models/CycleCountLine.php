<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Inventory\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleCountLine extends Model
{
    protected $fillable = [
        'cycle_count_id',
        'location_id',
        'item_id',
        'batch_id',
        'uom_id',
        'system_qty',
        'counted_qty',
        'variance',
    ];

    protected function casts(): array
    {
        return [
            'system_qty'  => 'decimal:4',
            'counted_qty' => 'decimal:4',
            'variance'    => 'decimal:4',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function count(): BelongsTo
    {
        return $this->belongsTo(CycleCount::class, 'cycle_count_id');
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

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function hasVariance(): bool
    {
        return $this->counted_qty !== null
            && bccomp((string) $this->variance, '0', 4) !== 0;
    }

    public function isCounted(): bool
    {
        return $this->counted_qty !== null;
    }
}
