<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ItemUom extends Model
{
    use LogsActivity;

    protected $table = 'item_uoms';

    protected $fillable = [
        'item_id',
        'uom_id',
        'factor_to_base',
        'is_default_purchase',
        'is_default_issue',
    ];

    protected function casts(): array
    {
        return [
            'factor_to_base'      => 'decimal:4',
            'is_default_purchase' => 'boolean',
            'is_default_issue'    => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
