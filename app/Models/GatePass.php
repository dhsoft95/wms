<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Outbound\GatePassStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GatePass extends Model
{
    use LogsActivity;

    protected $fillable = [
        'number', 'dispatch_id', 'status', 'exited_at', 'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'status'    => GatePassStatus::class,
            'exited_at' => 'datetime',
        ];
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
