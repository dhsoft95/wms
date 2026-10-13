<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContainerSizeType;
use App\Enums\ContainerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Container extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'number',
        'container_no',
        'seal_no',
        'size_type',
        'bl_no',
        'vessel',
        'voyage',
        'shipper_partner_id',
        'consignee_partner_id',
        'shipping_line_partner_id',
        'eta',
        'arrived_at',
        'arrival_seal_no',
        'seal_verified',
        'free_days',
        'yard_location_id',
        'status',
        'unloading_started_at',
        'unloaded_at',
        'released_at',
        'released_to_partner_id',
        'release_notes',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status'                 => ContainerStatus::class,
            'size_type'              => ContainerSizeType::class,
            'eta'                    => 'date',
            'arrived_at'             => 'datetime',
            'unloading_started_at'   => 'datetime',
            'unloaded_at'            => 'datetime',
            'released_at'            => 'datetime',
            'seal_verified'          => 'boolean',
            'free_days'              => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function shipperPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'shipper_partner_id');
    }

    public function consigneePartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'consignee_partner_id');
    }

    public function shippingLinePartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'shipping_line_partner_id');
    }

    public function yardLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'yard_location_id');
    }

    public function releasedToPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'released_to_partner_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function expectedLines(): HasMany
    {
        return $this->hasMany(ContainerExpectedLine::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ContainerStatusHistory::class)->orderBy('created_at');
    }

    public function yardMoves(): HasMany
    {
        return $this->hasMany(ContainerYardMove::class)->orderBy('created_at');
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(ContainerDiscrepancy::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ContainerDocument::class);
    }

    // ─── Computed Methods ─────────────────────────────────────────────────────

    /**
     * Number of days since container arrived. Returns 0 if not yet arrived.
     */
    public function dwellDays(): int
    {
        if ($this->arrived_at === null) {
            return 0;
        }

        return (int) $this->arrived_at->startOfDay()->diffInDays(now()->startOfDay());
    }

    /**
     * Number of days beyond free days (demurrage days). Returns 0 if within free period.
     */
    public function demurrageDays(): int
    {
        $freeDays = $this->free_days ?? Setting::get('default_free_days', 7);
        $dwell    = $this->dwellDays();

        return max(0, $dwell - (int) $freeDays);
    }

    /**
     * Returns 'ok', 'warning', or 'critical' based on dwell thresholds.
     */
    public function dwellStatus(): string
    {
        $dwell    = $this->dwellDays();
        $warning  = (int) Setting::get('container_dwell_warning_days', 7);
        $critical = (int) Setting::get('container_dwell_critical_days', 14);

        if ($dwell >= $critical) {
            return 'critical';
        }

        if ($dwell >= $warning) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * Whether the container's header fields can be edited.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [
            ContainerStatus::Expected,
            ContainerStatus::Arrived,
            ContainerStatus::Parked,
        ], true);
    }

    /**
     * Whether the container number can be changed (only while Expected).
     */
    public function containerNoEditable(): bool
    {
        return $this->status === ContainerStatus::Expected;
    }

    /**
     * Whether expected lines can be added/edited/deleted.
     */
    public function linesEditable(): bool
    {
        return in_array($this->status, [
            ContainerStatus::Expected,
            ContainerStatus::Arrived,
            ContainerStatus::Parked,
        ], true);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query): mixed
    {
        $activeValues = array_column(
            array_filter(ContainerStatus::cases(), fn(ContainerStatus $s) => $s->isActive()),
            'value'
        );

        return $query->whereIn('status', $activeValues);
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
