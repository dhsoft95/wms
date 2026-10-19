<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Outbound\AllocationRule;
use App\Enums\Outbound\DeliveryOrderPriority;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Models\Concerns\BelongsToCategoryUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DeliveryOrder extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, BelongsToCategoryUser;

    protected $fillable = [
        'number', 'virtual_warehouse_id', 'warehouse_id', 'department_id',
        'consignee_partner_id', 'delivery_address', 'required_date',
        'priority', 'allocation_rule', 'status', 'requested_by',
        'submitted_at', 'remarks', 'backorder_of_id',
        'mrn_number', 'mrn_file_path',
    ];

    protected function casts(): array
    {
        return [
            'status'          => DeliveryOrderStatus::class,
            'priority'        => DeliveryOrderPriority::class,
            'allocation_rule' => AllocationRule::class,
            'required_date'   => 'date',
            'submitted_at'    => 'datetime',
        ];
    }

    public function virtualWarehouse(): BelongsTo
    {
        return $this->belongsTo(VirtualWarehouse::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function consigneePartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'consignee_partner_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function backorderOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'backorder_of_id');
    }

    public function backorders(): HasMany
    {
        return $this->hasMany(self::class, 'backorder_of_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryOrderLine::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DeliveryOrderApproval::class);
    }

    public function pickLists(): HasMany
    {
        return $this->hasMany(PickList::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }

    public function proofOfDelivery(): HasOne
    {
        return $this->hasOne(ProofOfDelivery::class);
    }

    public function grns(): HasMany
    {
        return $this->hasMany(Grn::class);
    }

    public function isOverdue(): bool
    {
        return $this->required_date !== null
            && $this->required_date->isPast()
            && ! in_array($this->status, [
                DeliveryOrderStatus::Dispatched,
                DeliveryOrderStatus::Delivered,
                DeliveryOrderStatus::Cancelled,
            ], true);
    }

    public static function applyCategoryUserScope(Builder $builder, Collection $categoryIds, Collection $warehouseIds): void
    {
        $builder->whereIn('warehouse_id', $warehouseIds)
            ->whereHas('lines', fn ($q) => $q->whereHas('item', fn ($q2) => $q2->whereIn('category_id', $categoryIds)));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
