<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Enums\Inventory\MovementType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransaction extends Model
{
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    public $timestamps = false;

    protected $fillable = [
        'number',
        'type',
        'reference_type',
        'reference_id',
        'reference_no',
        'idempotency_key',
        'notes',
        'posted_by',
        'device',
        'posted_at',
        'reversed_by_transaction_id',
        'reverses_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'type'      => MovementType::class,
            'posted_at' => 'datetime',
        ];
    }

    // ─── Immutability ─────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (): never {
            throw new \RuntimeException('StockTransaction records are immutable.');
        });

        static::deleting(function (): never {
            throw new \RuntimeException('StockTransaction records are immutable.');
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversal(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'reversed_by_transaction_id');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class, 'reverses_transaction_id');
    }
}
