<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VirtualWarehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'department_id', 'warehouse_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_virtual_warehouse')
            ->withTimestamps();
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'default_virtual_warehouse_id');
    }

    public function isInUse(): bool
    {
        return $this->users()->exists() || $this->items()->withTrashed()->exists();
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    /**
     * Limit to VWs accessible by a given user.
     * Unrestricted roles see all (no filter added); restricted roles see only
     * the ones they are assigned to. Does NOT apply the BelongsToVirtualWarehouse
     * global scope — this model has no virtual_warehouse_id column.
     */
    public function scopeAccessibleBy($query, User $user): mixed
    {
        if ($user->canAccessAllVirtualWarehouses()) {
            return $query;
        }

        return $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
    }
}
