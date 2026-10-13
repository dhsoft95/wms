<?php

namespace App\Models;

use App\Enums\ReasonCodeAppliesTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ReasonCode extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['code', 'name', 'applies_to', 'is_active'];

    protected function casts(): array
    {
        return [
            'applies_to' => ReasonCodeAppliesTo::class,
            'is_active'  => 'boolean',
        ];
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    // ─── Activity Log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->dontSubmitEmptyLogs();
    }
}
