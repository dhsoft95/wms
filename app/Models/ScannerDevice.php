<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ScannerDevice extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'device_uid',
        'default_printer_id',
        'last_user_id',
        'last_seen_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'bool',
            'last_seen_at' => 'datetime',
        ];
    }

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    public function defaultPrinter(): BelongsTo
    {
        return $this->belongsTo(Printer::class, 'default_printer_id');
    }

    public function lastUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
