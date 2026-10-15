<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Inventory\StockTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Dispatch extends Model
{
    use LogsActivity;

    protected $fillable = [
        'number', 'delivery_order_id', 'vehicle_no', 'driver_name', 'driver_phone',
        'driver_id_no', 'transporter_partner_id', 'seal_no', 'dispatched_at',
        'dispatched_by', 'stock_transaction_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
        ];
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function transporterPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'transporter_partner_id');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function stockTransaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DispatchItem::class);
    }

    public function gatePass(): HasOne
    {
        return $this->hasOne(GatePass::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
