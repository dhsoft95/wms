<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRequest extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'date_from',
        'date_to',
        'status',
        'file_path',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'immutable_date',
            'date_to'   => 'immutable_date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isProcessing(): bool { return $this->status === 'processing'; }
    public function isReady(): bool      { return $this->status === 'ready'; }
    public function isFailed(): bool     { return $this->status === 'failed'; }
}
