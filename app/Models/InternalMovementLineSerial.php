<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalMovementLineSerial extends Model
{
    protected $fillable = [
        'internal_movement_line_id',
        'serial_number',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(InternalMovementLine::class, 'internal_movement_line_id');
    }
}
