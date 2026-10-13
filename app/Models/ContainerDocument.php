<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContainerDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ContainerDocument extends Model
{
    use LogsActivity;

    protected $fillable = [
        'container_id',
        'type',
        'original_name',
        'path',
        'mime',
        'size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContainerDocumentType::class,
            'size' => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ─── Methods ──────────────────────────────────────────────────────────────

    /**
     * Format the file size in a human-readable form (e.g. "1.2 MB").
     */
    public function sizeHuman(): string
    {
        $bytes = $this->size;

        if ($bytes < 1_024) {
            return $bytes . ' B';
        }

        if ($bytes < 1_048_576) {
            return round($bytes / 1_024, 1) . ' KB';
        }

        if ($bytes < 1_073_741_824) {
            return round($bytes / 1_048_576, 1) . ' MB';
        }

        return round($bytes / 1_073_741_824, 2) . ' GB';
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
