<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Enums\Inventory\LpnStatus;
use App\Models\Grn;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lpn extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'status',
        'warehouse_id',
        'current_location_id',
        'grn_id',
        'printed_at',
        'print_count',
    ];

    protected function casts(): array
    {
        return [
            'status'      => LpnStatus::class,
            'printed_at'  => 'datetime',
            'print_count' => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function grn(): BelongsTo
    {
        return $this->belongsTo(Grn::class);
    }
}
