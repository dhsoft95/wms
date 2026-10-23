<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Inventory\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockStatusRequestLine extends Model
{
    protected $fillable = [
        'stock_status_request_id',
        'item_id',
        'location_id',
        'batch_id',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function stockStatusRequest(): BelongsTo
    {
        return $this->belongsTo(StockStatusRequest::class);
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

    public function lineSerials(): HasMany
    {
        return $this->hasMany(StockStatusRequestLineSerial::class);
    }
}
