<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inbound\PurchaseOrderApprovalAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderApproval extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'approval_chain_id',
        'item_category_id',
        'level',
        'user_id',
        'action',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'action' => PurchaseOrderApprovalAction::class,
            'level'  => 'integer',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function approvalChain(): BelongsTo
    {
        return $this->belongsTo(ApprovalChain::class);
    }

    public function itemCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCategoryLevel(): bool
    {
        return $this->approval_chain_id === null;
    }
}
