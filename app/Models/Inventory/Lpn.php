<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Enums\Inventory\LpnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lpn extends Model
{
    protected $fillable = [
        'code',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => LpnStatus::class,
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
