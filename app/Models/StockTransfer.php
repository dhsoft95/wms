<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StockTransfer extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = [
        'number',
        'from_virtual_warehouse_id',
        'to_virtual_warehouse_id',
        'created_by',
        'approved_by',
        'status',
        'notes',
        'rejection_reason',
        'posted_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'status'    => TransferStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending($query): mixed
    {
        return $query->where('status', TransferStatus::Submitted);
    }

    public function scopeForVirtualWarehouse($query, int $vwId): mixed
    {
        return $query->where(function ($q) use ($vwId) {
            $q->where('from_virtual_warehouse_id', $vwId)
              ->orWhere('to_virtual_warehouse_id', $vwId);
        });
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
