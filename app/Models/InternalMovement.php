<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MovementStatus;
use App\Models\Concerns\BelongsToCategoryUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InternalMovement extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, BelongsToCategoryUser;

    protected $fillable = [
        'number',
        'virtual_warehouse_id',
        'status',
        'notes',
        'created_by',
        'executed_by',
        'posted_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'status'      => MovementStatus::class,
            'posted_at'   => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function lines(): HasMany
    {
        return $this->hasMany(InternalMovementLine::class);
    }

    public function virtualWarehouse(): BelongsTo
    {
        return $this->belongsTo(VirtualWarehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public static function applyCategoryUserScope(Builder $builder, Collection $categoryIds, Collection $warehouseIds): void
    {
        $builder->whereHas('lines', function ($q) use ($categoryIds, $warehouseIds) {
            $q->whereHas('item', fn ($q2) => $q2->whereIn('category_id', $categoryIds))
              ->whereHas('fromLocation', fn ($q2) => $q2->whereIn('warehouse_id', $warehouseIds));
        });
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
