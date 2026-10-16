<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MovementStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InternalMovement extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'number',
        'virtual_warehouse_id',
        'status',
        'notes',
        'created_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'status'    => MovementStatus::class,
            'posted_at' => 'datetime',
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

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
