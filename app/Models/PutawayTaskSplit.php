<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Inventory\StockTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PutawayTaskSplit extends Model
{
    protected $table = 'putaway_task_splits';

    protected $fillable = [
        'putaway_task_id',
        'location_id',
        'qty',
        'serial_nos',
        'stock_transaction_id',
        'posted_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'qty'        => 'decimal:4',
            'serial_nos' => 'array',
            'posted_at'  => 'datetime',
        ];
    }

    public function putawayTask(): BelongsTo
    {
        return $this->belongsTo(PutawayTask::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function stockTransaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
