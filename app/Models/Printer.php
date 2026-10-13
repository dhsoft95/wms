<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrinterKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Printer extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'kind',
        'ip',
        'port',
        'dpi',
        'location_note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kind'      => PrinterKind::class,
            'is_active' => 'bool',
            'port'      => 'int',
            'dpi'       => 'int',
        ];
    }

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(LabelPrintJob::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
