<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GrnSource;
use App\Enums\GrnStatus;
use App\Enums\GrnVarianceType;
use App\Models\Inventory\Lpn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Grn extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'grns';

    protected $fillable = [
        'number',
        'source',
        'container_id',
        'supplier_partner_id',
        'customer_partner_id',
        'warehouse_id',
        'delivery_order_id',
        'receiving_dock_location_id',
        'status',
        'received_by',
        'confirmed_by',
        'confirmed_at',
        'closed_at',
        'notes',
        'delivery_note_ref',
        'expected_date',
    ];

    protected function casts(): array
    {
        return [
            'source'        => GrnSource::class,
            'status'        => GrnStatus::class,
            'confirmed_at'  => 'datetime',
            'closed_at'     => 'datetime',
            'expected_date' => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function supplierPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_partner_id');
    }

    public function customerPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_partner_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receivingDock(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'receiving_dock_location_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GrnLine::class);
    }

    public function receipts(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(GrnReceipt::class, GrnLine::class);
    }

    public function varianceApprovals(): HasMany
    {
        return $this->hasMany(GrnVarianceApproval::class);
    }

    public function putawayTasks(): HasMany
    {
        return $this->hasMany(PutawayTask::class);
    }

    public function lpns(): HasMany
    {
        return $this->hasMany(Lpn::class);
    }

    // ─── Business Methods ─────────────────────────────────────────────────────

    public function isEditable(): bool
    {
        return in_array($this->status, [GrnStatus::Draft, GrnStatus::Receiving], true);
    }

    public function needsVarianceApproval(): bool
    {
        return $this->lines()
            ->where('variance_type', '!=', GrnVarianceType::None->value)
            ->exists();
    }

    public function hasPendingApproval(): bool
    {
        return $this->varianceApprovals()
            ->whereNull('decision')
            ->exists();
    }

    public function allLinesConfirmed(): bool
    {
        return $this->lines()->exists()
            && $this->lines()->where('variance_type', '!=', GrnVarianceType::None->value)
                ->whereNull('variance_reason_code_id')
                ->doesntExist();
    }

    public function totalExpectedQty(): string
    {
        return (string) $this->lines()->sum('expected_qty');
    }

    public function totalReceivedQty(): string
    {
        $good    = $this->lines()->sum('received_good_qty');
        $damaged = $this->lines()->sum('received_damaged_qty');

        return (string) bcadd((string) $good, (string) $damaged, 4);
    }

    public function progressPercent(): int
    {
        $expected = (float) $this->totalExpectedQty();
        if ($expected <= 0) {
            return 0;
        }

        $received = (float) $this->totalReceivedQty();

        return (int) min(100, round($received / $expected * 100));
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query): mixed
    {
        return $query->whereNotIn('status', [GrnStatus::Closed->value, GrnStatus::Cancelled->value]);
    }

    public function scopeByStatus($query, GrnStatus $status): mixed
    {
        return $query->where('status', $status->value);
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
