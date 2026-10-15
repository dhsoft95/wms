<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Inventory\Batch;
use App\Models\Inventory\Lpn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchItem extends Model
{
    protected $fillable = [
        'dispatch_id', 'lpn_id', 'item_id', 'batch_id', 'qty', 'is_scanned',
    ];

    protected function casts(): array
    {
        return [
            'qty'        => 'decimal:4',
            'is_scanned' => 'boolean',
        ];
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function lpn(): BelongsTo
    {
        return $this->belongsTo(Lpn::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
