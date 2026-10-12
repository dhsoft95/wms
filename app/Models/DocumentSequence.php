<?php

namespace App\Models;

use App\Enums\DocumentSequenceType;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DocumentSequence extends Model
{
    use LogsActivity;

    protected $fillable = [
        'type',
        'prefix',
        'include_year',
        'padding',
        'next_number',
        'reset_yearly',
        'last_reset_year',
    ];

    protected function casts(): array
    {
        return [
            'type'            => DocumentSequenceType::class,
            'include_year'    => 'boolean',
            'reset_yearly'    => 'boolean',
            'padding'         => 'integer',
            'next_number'     => 'integer',
            'last_reset_year' => 'integer',
        ];
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
