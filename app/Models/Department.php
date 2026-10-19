<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'code', 'is_active', 'head_user_id', 'report_email'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function virtualWarehouses(): HasMany
    {
        return $this->hasMany(VirtualWarehouse::class);
    }

    public function approvalChains(): HasMany
    {
        return $this->hasMany(ApprovalChain::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function activeUserCount(): int
    {
        return $this->users()->where('status', 'active')->count();
    }

    public function isInUse(): bool
    {
        return $this->users()->exists() || $this->virtualWarehouses()->exists();
    }
}
