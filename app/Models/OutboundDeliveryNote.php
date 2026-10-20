<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Outbound\OdnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OutboundDeliveryNote extends Model
{
    use LogsActivity;

    protected $fillable = [
        'number',
        'dispatch_id',
        'delivery_order_id',
        'status',
        'from_warehouse_id',
        'vehicle_reg_no',
        'container_no',
        'container_size',
        'seal_no',
        'driver_name',
        'driver_phone',
        'transporter_name',
        'remarks',
        'internal_notes',
        'generated_by',
        'generated_at',
        'pod_received_by',
        'pod_received_at',
        'pod_file_path',
        'cancel_reason',
        'cancelled_by',
        'cancelled_at',
        'printed_count',
    ];

    protected function casts(): array
    {
        return [
            'status'          => OdnStatus::class,
            'generated_at'    => 'datetime',
            'pod_received_at' => 'datetime',
            'cancelled_at'    => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OutboundDeliveryNoteLine::class, 'odn_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
