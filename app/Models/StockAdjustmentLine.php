<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Inventory\Batch;
use App\Models\Inventory\Lpn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentLine extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'item_id',
        'location_id',
        'batch_id',
        'lpn_id',
        'reason_code_id',
        'qty',
        'uom_id',
        'serials',
    ];

    protected function casts(): array
    {
        return [
            'qty'     => 'decimal:4',
            'serials' => 'array',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
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

    public function lpn(): BelongsTo
    {
        return $this->belongsTo(Lpn::class);
    }

    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
