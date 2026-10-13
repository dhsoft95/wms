<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ContainerExpectedLine extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'container_id',
        'line_no',
        'item_id',
        'raw_sku',
        'raw_description',
        'qty',
        'uom_id',
        'batch_no',
        'expiry_date',
        'weight_kg',
        'virtual_warehouse_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty'         => 'decimal:4',
            'weight_kg'   => 'decimal:3',
            'expiry_date' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function virtualWarehouse(): BelongsTo
    {
        return $this->belongsTo(VirtualWarehouse::class);
    }

    // ─── Methods ──────────────────────────────────────────────────────────────

    /**
     * Returns true when the line has been mapped to a known item.
     */
    public function isMapped(): bool
    {
        return $this->item_id !== null;
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
