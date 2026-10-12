<?php

namespace App\Models;

use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Location extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'warehouse_id',
        'parent_id',
        'level',
        'segment_code',
        'code',
        'type',
        'status',
        'max_pallets',
        'max_weight_kg',
        'barcode',
        'path',
        'is_pickable',
        'blocked_reason',
        'blocked_by',
        'blocked_at',
    ];

    protected function casts(): array
    {
        return [
            'level'         => LocationLevel::class,
            'type'          => LocationType::class,
            'status'        => LocationStatus::class,
            'is_pickable'   => 'boolean',
            'blocked_at'    => 'datetime',
            'max_weight_kg' => 'decimal:3',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'blocked_by');
    }

    // ─── Methods ──────────────────────────────────────────────────────────────

    public function hasStock(): bool
    {
        return \App\Models\Inventory\StockBalance::where('location_id', $this->id)
            ->where('qty_on_hand', '>', 0)->exists();
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function descendants(): Collection
    {
        return Location::where('path', 'like', $this->path . $this->id . '/%')->get();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query): mixed
    {
        return $query->where('status', LocationStatus::Active);
    }

    public function scopePickable($query): mixed
    {
        return $query->where('is_pickable', true)->where('status', LocationStatus::Active);
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
