<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Batch extends Model
{
    protected $fillable = [
        'item_id',
        'batch_no',
        'expiry_date',
        'manufactured_date',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date'       => 'date',
            'manufactured_date' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    // ─── Methods ──────────────────────────────────────────────────────────────

    public function hasMovements(): bool
    {
        return StockMovement::where('batch_id', $this->id)->exists();
    }
}
