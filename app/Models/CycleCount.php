<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CycleCountStatus;
use App\Models\Concerns\BelongsToCategoryUser;
use App\Models\Concerns\BelongsToVirtualWarehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CycleCount extends Model
{
    use BelongsToVirtualWarehouse, LogsActivity, SoftDeletes, BelongsToCategoryUser;

    protected $fillable = [
        'number',
        'virtual_warehouse_id',
        'created_by',
        'approved_by',
        'cancelled_by',
        'status',
        'notes',
        'posted_at',
        'cancelled_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'status'       => CycleCountStatus::class,
            'posted_at'    => 'datetime',
            'cancelled_at' => 'datetime',
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

    public static function applyCategoryUserScope(Builder $builder, Collection $categoryIds, Collection $warehouseIds): void
    {
        $builder->whereHas('lines', function ($q) use ($categoryIds, $warehouseIds) {
            $q->whereHas('item', fn ($q2) => $q2->whereIn('category_id', $categoryIds))
              ->whereHas('location', fn ($q2) => $q2->whereIn('warehouse_id', $warehouseIds));
        });
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
