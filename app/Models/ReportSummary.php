<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSummary extends Model
{
    protected $fillable = [
        'report_type',
        'scope_hash',
        'period_start',
        'period_end',
        'language',
        'summary_text',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end'   => 'date',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
