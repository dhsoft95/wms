<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Enums\Inventory\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    protected $fillable = [
        'stock_balance_id',
        'qty',
        'reference_type',
        'reference_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'qty'    => 'decimal:4',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function balance(): BelongsTo
    {
        return $this->belongsTo(StockBalance::class, 'stock_balance_id');
    }
}
