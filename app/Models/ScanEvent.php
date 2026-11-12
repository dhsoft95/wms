<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanEvent extends Model
{
    use MassPrunable;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'scanner_device_id',
        'route_name',
        'raw_value',
        'resolved_type',
        'resolved_id',
        'success',
        'error_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'success'    => 'bool',
            'created_at' => 'datetime',
        ];
    }

    public function prunable(): \Illuminate\Database\Eloquent\Builder
    {
        return static::where('created_at', '<', now()->subDays(180));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scannerDevice(): BelongsTo
    {
        return $this->belongsTo(ScannerDevice::class);
    }
}
