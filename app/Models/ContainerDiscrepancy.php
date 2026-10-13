<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContainerDiscrepancyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ContainerDiscrepancy extends Model
{
    use LogsActivity;

    protected $fillable = [
        'container_id',
        'expected_line_id',
        'type',
        'description',
        'reason_code_id',
        'reported_by',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'type'        => ContainerDiscrepancyType::class,
            'resolved_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function expectedLine(): BelongsTo
    {
        return $this->belongsTo(ContainerExpectedLine::class, 'expected_line_id');
    }

    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ─── Methods ──────────────────────────────────────────────────────────────

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
