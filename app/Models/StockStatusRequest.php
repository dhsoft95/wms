<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inventory\StockStatusRequestStatus;
use App\Enums\Inventory\StockStatusRequestType;
use App\Models\Concerns\BelongsToVirtualWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StockStatusRequest extends Model
{
    use BelongsToVirtualWarehouse, LogsActivity;

    protected $fillable = [
        'number',
        'virtual_warehouse_id',
        'department_id',
        'type',
        'status',
        'reason',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'created_by',
        'approved_by',
        'rejected_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'type'         => StockStatusRequestType::class,
            'status'       => StockStatusRequestStatus::class,
            'submitted_at' => 'datetime',
            'approved_at'  => 'datetime',
            'rejected_at'  => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function virtualWarehouse(): BelongsTo
    {
        return $this->belongsTo(VirtualWarehouse::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockStatusRequestLine::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(StockStatusRequestApproval::class);
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
