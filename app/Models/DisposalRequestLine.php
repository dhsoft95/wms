<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inventory\StockStatus;
use App\Models\Inventory\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisposalRequestLine extends Model
{
    protected $fillable = [
        'disposal_request_id',
        'item_id',
        'location_id',
        'batch_id',
        'qty',
        'value',
        'reason',
        'stock_status',
    ];

    protected function casts(): array
    {
        return [
            'qty'          => 'decimal:4',
            'value'        => 'decimal:4',
            'stock_status' => StockStatus::class,
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function disposalRequest(): BelongsTo
    {
        return $this->belongsTo(DisposalRequest::class);
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
        return $this->hasMany(DisposalRequestLineSerial::class);
    }
}
