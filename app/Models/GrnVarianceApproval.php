<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrnVarianceApproval extends Model
{
    protected $table = 'grn_variance_approvals';

    protected $fillable = [
        'grn_id',
        'requested_by',
        'decided_by',
        'decision',
        'notes',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function grn(): BelongsTo
    {
        return $this->belongsTo(Grn::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    // ─── Business Methods ─────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->decision === null;
    }

    public function isApproved(): bool
    {
        return $this->decision === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->decision === 'rejected';
    }
}
