<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderApproval extends Model
{
    protected $fillable = [
        'delivery_order_id', 'approval_chain_id', 'level', 'approver_role',
        'decided_by', 'decision', 'notes', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function approvalChain(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ApprovalChain::class);
    }
}
