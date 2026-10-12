<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportMode;
use App\Enums\ImportStatus;
use App\Enums\ImportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DataImport extends Model
{
    use LogsActivity;

    protected $fillable = [
        'type',
        'original_filename',
        'stored_path',
        'status',
        'mode',
        'total_rows',
        'valid_rows',
        'error_rows',
        'created_count',
        'updated_count',
        'errors',
        'summary',
        'uploaded_by',
        'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'type'    => ImportType::class,
            'status'  => ImportStatus::class,
            'mode'    => ImportMode::class,
            'errors'  => 'array',
            'summary' => 'array',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function hasErrors(): bool
    {
        return $this->error_rows > 0;
    }

    public function canConfirm(): bool
    {
        return $this->status->canConfirm() && ! $this->hasErrors();
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'confirmed_by'])
            ->dontSubmitEmptyLogs();
    }
}
