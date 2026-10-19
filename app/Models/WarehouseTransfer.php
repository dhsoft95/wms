<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Transfers\WarehouseTransferStatus;
use App\Models\Concerns\BelongsToCategoryUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WarehouseTransfer extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, BelongsToCategoryUser;

    protected $table = 'warehouse_transfers';

    protected $fillable = [
        'number',
        'from_warehouse_id',
        'to_warehouse_id',
        'from_virtual_warehouse_id',
        'to_virtual_warehouse_id',
        'status',
        'delivery_order_id',
        'purchase_order_id',
        'notes',
        'rejection_reason',
        'created_by',
        'accepted_by',
        'dispatched_at',
        'received_at',
        'idempotency_key',
        'accepted_at',
        'dn_number',
        'rcn_number',
    ];

    protected function casts(): array
    {
        return [
            'status'        => WarehouseTransferStatus::class,
            'dispatched_at' => 'datetime',
            'received_at'   => 'datetime',
            'accepted_at'   => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function fromVirtualWarehouse(): BelongsTo
    {
        return $this->belongsTo(VirtualWarehouse::class, 'from_virtual_warehouse_id');
    }

    public function toVirtualWarehouse(): BelongsTo
    {
        return $this->belongsTo(VirtualWarehouse::class, 'to_virtual_warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WarehouseTransferLine::class);
    }

    // ─── Category / User scope ────────────────────────────────────────────────

    public static function applyCategoryUserScope(Builder $builder, Collection $categoryIds, Collection $warehouseIds): void
    {
        $builder->where(function (Builder $q) use ($warehouseIds) {
            $q->whereIn('from_warehouse_id', $warehouseIds)
              ->orWhereIn('to_warehouse_id', $warehouseIds);
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isInUse(): bool
    {
        return $this->lines()->exists();
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
