<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRequest extends Model
{
    protected $fillable = [
        'user_id',
        'scheduled_report_id',
        'type',
        'date_from',
        'date_to',
        'filters',
        'format',
        'row_count',
        'status',
        'file_path',
        'notified_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'date_from'    => 'immutable_date',
            'date_to'      => 'immutable_date',
            'filters'      => 'array',
            'notified_at'  => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scheduledReport(): BelongsTo
    {
        return $this->belongsTo(ScheduledReport::class);
    }

    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isProcessing(): bool { return $this->status === 'processing'; }
    public function isReady(): bool      { return $this->status === 'ready'; }
    public function isFailed(): bool     { return $this->status === 'failed'; }

    public function isRegistryReport(): bool { return $this->filters !== null; }

    public function needsNotification(): bool
    {
        return $this->isReady() && $this->notified_at === null;
    }
}
