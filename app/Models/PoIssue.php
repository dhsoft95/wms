<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inbound\PoIssueStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PoIssue extends Model
{
    use LogsActivity;

    protected $table = 'po_issues';

    protected $fillable = [
        'purchase_order_id',
        'grn_id',
        'raised_by',
        'description',
        'attachment_path',
        'status',
        'resolved_by',
        'resolution_notes',
        'stock_adjustment_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status'      => PoIssueStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function grn(): BelongsTo
    {
        return $this->belongsTo(Grn::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function isOpen(): bool
    {
        return $this->status === PoIssueStatus::Open;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
