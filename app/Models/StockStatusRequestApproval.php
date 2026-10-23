<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockStatusRequestApproval extends Model
{
    protected $fillable = [
        'stock_status_request_id',
        'approval_chain_id',
        'level',
        'user_id',
        'action',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
        ];
    }

    public function stockStatusRequest(): BelongsTo
    {
        return $this->belongsTo(StockStatusRequest::class);
    }

    public function approvalChain(): BelongsTo
    {
        return $this->belongsTo(ApprovalChain::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
