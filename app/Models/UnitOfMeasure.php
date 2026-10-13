<?php

namespace App\Models;

use App\Enums\UomType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class UnitOfMeasure extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'units_of_measure';

    protected $fillable = ['code', 'name', 'type', 'is_active'];

    protected function casts(): array
    {
        return [
            'type'      => UomType::class,
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'base_uom_id');
    }

    public function itemUoms(): HasMany
    {
        return $this->hasMany(ItemUom::class, 'uom_id');
    }

    public function itemBarcodes(): HasMany
    {
        return $this->hasMany(ItemBarcode::class, 'uom_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    // ─── In-use check ─────────────────────────────────────────────────────────

    public function isInUse(): bool
    {
        return $this->items()->exists()
            || $this->itemUoms()->exists()
            || $this->itemBarcodes()->exists();
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
