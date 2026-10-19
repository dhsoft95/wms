<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalChainUser extends Model
{
    protected $fillable = ['approval_chain_id', 'user_id'];

    public function chain(): BelongsTo
    {
        return $this->belongsTo(ApprovalChain::class, 'approval_chain_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
