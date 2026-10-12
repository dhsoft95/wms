<?php

namespace App\Models;

use App\Enums\ItemBarcodeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ItemBarcode extends Model
{
    use LogsActivity;

    protected $table = 'item_barcodes';

    protected $fillable = ['item_id', 'barcode', 'uom_id', 'type', 'is_primary'];

    protected function casts(): array
    {
        return [
            'type'       => ItemBarcodeType::class,
            'is_primary' => 'boolean',
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
