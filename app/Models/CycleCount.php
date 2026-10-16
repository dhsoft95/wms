<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CycleCountStatus;
use App\Models\Concerns\BelongsToVirtualWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CycleCount extends Model
{
    use BelongsToVirtualWarehouse, LogsActivity, SoftDeletes;

    protected $fillable = [
        'number',
        'virtual_warehouse_id',
        'created_by',
        'approved_by',
        'status',
        'notes',
        'posted_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'status'    => CycleCountStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function lines(): HasMany
    {
        return $this->hasMany(CycleCountLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query): mixed
    {
        return $query->whereIn('status', [
            CycleCountStatus::Open,
            CycleCountStatus::Counting,
            CycleCountStatus::VarianceReview,
        ]);
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
