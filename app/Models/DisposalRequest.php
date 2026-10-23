<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inventory\DisposalMethod;
use App\Enums\Inventory\DisposalStatus;
use App\Enums\Inventory\DestroyMethod;
use App\Models\Concerns\BelongsToVirtualWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DisposalRequest extends Model
{
    use BelongsToVirtualWarehouse, LogsActivity;

    protected $fillable = [
        'number',
        'virtual_warehouse_id',
        'department_id',
        'method',
        'reason',
        'status',
        'total_value',
        'submitted_at',
        'approved_at',
        'moved_at',
        'completed_at',
        'rejected_at',
        'created_by',
        'approved_by',
        'moved_by',
        'completed_by',
        'rejected_by',
        'rejection_reason',
        // Auction
        'buyer_name',
        'sale_price',
        'sale_date',
        'receipt_file_path',
        // Destroy
        'destroy_method',
        'destroy_date',
        'witness_name',
        'certificate_file_path',
    ];

    protected function casts(): array
    {
        return [
            'status'       => DisposalStatus::class,
            'method'       => DisposalMethod::class,
            'destroy_method' => DestroyMethod::class,
            'total_value'  => 'decimal:4',
            'sale_price'   => 'decimal:4',
            'submitted_at' => 'datetime',
            'approved_at'  => 'datetime',
            'moved_at'     => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at'  => 'datetime',
            'sale_date'    => 'date',
            'destroy_date' => 'date',
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

    public function movedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moved_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DisposalRequestLine::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DisposalRequestApproval::class);
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
