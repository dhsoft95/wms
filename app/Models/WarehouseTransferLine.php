<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseTransferLine extends Model
{
    protected $fillable = [
        'warehouse_transfer_id',
        'line_no',
        'source_location_id',
        'item_id',
        'uom_id',
        'qty',
        'qty_base',
        'batch_no',
        'expiry_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty'         => 'decimal:4',
            'qty_base'    => 'decimal:4',
            'expiry_date' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function warehouseTransfer(): BelongsTo
    {
        return $this->belongsTo(WarehouseTransfer::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
