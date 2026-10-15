<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Warehouse extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'address',
        'is_active',
        'default_receiving_dock_location_id',
        'default_quarantine_location_id',
        'default_staging_location_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function rootLocations(): HasMany
    {
        return $this->hasMany(Location::class)->whereNull('parent_id');
    }

    public function defaultReceivingDock(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_receiving_dock_location_id');
    }

    public function defaultQuarantineLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_quarantine_location_id');
    }

    public function defaultStagingLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_staging_location_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    // ─── In-use check ─────────────────────────────────────────────────────────

    public function isInUse(): bool
    {
        return $this->locations()->exists();
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
