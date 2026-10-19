<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ItemCategory extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'item_categories';

    protected $fillable = ['name', 'parent_id', 'is_active', 'adjustment_requires_approval'];

    protected function casts(): array
    {
        return [
            'is_active'                     => 'boolean',
            'parent_id'                     => 'integer',
            'adjustment_requires_approval'  => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ItemCategory::class, 'parent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'category_id');
    }

    public function categoryUsers(): HasMany
    {
        return $this->hasMany(ItemCategoryUser::class, 'item_category_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    // ─── In-use check ─────────────────────────────────────────────────────────

    public function isInUse(): bool
    {
        return $this->items()->exists();
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
