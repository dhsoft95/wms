<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Outbound\PickListStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PickList extends Model
{
    use LogsActivity;

    protected $fillable = [
        'number', 'delivery_order_id', 'status', 'assigned_to',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status'       => PickListStatus::class,
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(PickTask::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
