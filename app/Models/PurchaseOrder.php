<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inbound\PackageType;
use App\Enums\Inbound\PurchaseOrderSource;
use App\Enums\Inbound\PurchaseOrderStatus;
use App\Models\Concerns\BelongsToCategoryUser;
use App\Models\Concerns\BelongsToVirtualWarehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PurchaseOrder extends Model
{
    use HasFactory, LogsActivity, BelongsToVirtualWarehouse, BelongsToCategoryUser;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'number',
        'customer_po_ref',
        'po_file_path',
        'source',
        'package_type',
        'virtual_warehouse_id',
        'warehouse_id',
        'department_id',
        'expected_date',
        'supplier_partner_id',
        'consignee_partner_id',
        'currency',
        'requested_by',
        'assigned_supervisor_id',
        'assigned_clerk_id',
        'status',
        'container_id',
        'original_do_id',
        'submitted_at',
        'approved_at',
        'accepted_at',
        'assigned_supervisor_at',
        'assigned_clerk_at',
        'rejection_reason',
        'acceptance_rejection_reason',
        'rejected_at',
        'arrived_at',
        'received_at',
        'putaway_validated_at',
        'verified_at',
        'closed_at',
        'cancelled_at',
        'notes',
        'is_special_treatment',
        'special_treatment_reason',
    ];

    protected function casts(): array
    {
        return [
            'status'                 => PurchaseOrderStatus::class,
            'source'                 => PurchaseOrderSource::class,
            'package_type'           => PackageType::class,
            'expected_date'          => 'date',
            'submitted_at'           => 'datetime',
            'approved_at'            => 'datetime',
            'accepted_at'            => 'datetime',
            'assigned_supervisor_at' => 'datetime',
            'assigned_clerk_at'      => 'datetime',
            'rejected_at'            => 'datetime',
            'arrived_at'             => 'datetime',
            'received_at'            => 'datetime',
            'putaway_validated_at'   => 'datetime',
            'verified_at'            => 'datetime',
            'closed_at'              => 'datetime',
            'cancelled_at'           => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function supplierPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_partner_id');
    }

    public function consigneePartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'consignee_partner_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignedSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_supervisor_id');
    }

    public function assignedClerk(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_clerk_id');
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function originalDo(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'original_do_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function grns(): HasMany
    {
        return $this->hasMany(Grn::class, 'purchase_order_id');
    }

    public function grnLines(): HasManyThrough
    {
        return $this->hasManyThrough(
            \App\Models\GrnLine::class,
            Grn::class,
            'purchase_order_id',
            'grn_id',
        );
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PurchaseOrderApproval::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PurchaseOrderAssignment::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(PoIssue::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): mixed
    {
        return $query->whereNotIn('status', [
            PurchaseOrderStatus::Cancelled->value,
            PurchaseOrderStatus::VerifiedClosed->value,
        ]);
    }

    public function scopeForWarehouse(Builder $query, int $warehouseId): mixed
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    // ─── BelongsToCategoryUser ────────────────────────────────────────────────

    public static function applyCategoryUserScope(Builder $builder, Collection $categoryIds, Collection $warehouseIds): void
    {
        $builder->whereHas('lines', function ($q) use ($categoryIds, $warehouseIds) {
            $q->whereHas('item', fn ($q2) => $q2->whereIn('category_id', $categoryIds));
        })->where(fn ($q) => $q->whereIn('warehouse_id', $warehouseIds)->orWhereNull('warehouse_id'));
    }

    // ─── Business Methods ─────────────────────────────────────────────────────

    public function totalAmount(): string
    {
        return (string) $this->lines()->sum('line_total');
    }

    public function isInUse(): bool
    {
        return $this->lines()->exists();
    }

    public function hasOpenIssues(): bool
    {
        return $this->issues()->where('status', 'open')->exists();
    }

    public function allLinesReceived(): bool
    {
        return $this->lines()->where('qty', '>', 0)->exists()
            && $this->grns()
                ->whereIn('status', ['received', 'putaway_in_progress', 'putaway_complete', 'closed'])
                ->exists();
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
