<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_lines';

    protected $fillable = [
        'purchase_order_id',
        'line_no',
        'item_id',
        'uom_id',
        'part_number',
        'qty',
        'qty_base',
        'unit_price',
        'currency',
        'line_total',
        'batch_no',
        'expiry_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty'         => 'decimal:4',
            'qty_base'    => 'decimal:4',
            'unit_price'  => 'decimal:4',
            'line_total'  => 'decimal:4',
            'expiry_date' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function purchaseOrderLineGrnLines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GrnLine::class, 'purchase_order_line_id');
    }

    public function getItemPartNumberAttribute(): ?string
    {
        return $this->item?->part_number;
    }
}
