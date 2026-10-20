<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundDeliveryNoteLine extends Model
{
    protected $fillable = [
        'odn_id',
        'do_line_id',
        'line_no',
        'sku',
        'description',
        'batch_no',
        'expiry_date',
        'qty',
        'uom_code',
        'serial_numbers',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date'    => 'date',
            'serial_numbers' => 'array',
            'qty'            => 'decimal:4',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function odn(): BelongsTo
    {
        return $this->belongsTo(OutboundDeliveryNote::class, 'odn_id');
    }
}
