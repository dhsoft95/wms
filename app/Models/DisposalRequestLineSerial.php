<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Inventory\SerialNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisposalRequestLineSerial extends Model
{
    protected $fillable = [
        'disposal_request_line_id',
        'serial_number_id',
    ];

    public function disposalRequestLine(): BelongsTo
    {
        return $this->belongsTo(DisposalRequestLine::class);
    }

    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(SerialNumber::class);
    }
}
