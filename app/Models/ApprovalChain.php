<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Inbound\ApprovalChainDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalChain extends Model
{
    protected $fillable = [
        'department_id',
        'document_type',
        'level',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => ApprovalChainDocumentType::class,
            'level'         => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function chainUsers(): HasMany
    {
        return $this->hasMany(ApprovalChainUser::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'approval_chain_users');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PurchaseOrderApproval::class);
    }
}
