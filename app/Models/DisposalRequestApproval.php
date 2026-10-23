<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisposalRequestApproval extends Model
{
    protected $fillable = [
        'disposal_request_id',
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

    public function disposalRequest(): BelongsTo
    {
        return $this->belongsTo(DisposalRequest::class);
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
