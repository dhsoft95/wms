<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Inventory\SerialNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockStatusRequestLineSerial extends Model
{
    protected $fillable = [
        'stock_status_request_line_id',
        'serial_number_id',
    ];

    public function stockStatusRequestLine(): BelongsTo
    {
        return $this->belongsTo(StockStatusRequestLine::class);
    }

    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(SerialNumber::class);
    }
}
