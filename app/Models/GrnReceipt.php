<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReceiptCondition;
use App\Models\Inventory\Lpn;
use App\Models\Inventory\SerialNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GrnReceipt extends Model
{
    protected $table = 'grn_receipts';

    protected $fillable = [
        'grn_line_id',
        'qty',
        'uom_id',
        'batch_no',
        'expiry_date',
        'condition',
        'lpn_id',
        'received_by',
        'device',
    ];

    protected function casts(): array
    {
        return [
            'qty'         => 'decimal:4',
            'expiry_date' => 'date',
            'condition'   => ReceiptCondition::class,
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function line(): BelongsTo
    {
        return $this->belongsTo(GrnLine::class, 'grn_line_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function lpn(): BelongsTo
    {
        return $this->belongsTo(Lpn::class, 'lpn_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function serials(): BelongsToMany
    {
        return $this->belongsToMany(
            SerialNumber::class,
            'grn_receipt_serials',
            'grn_receipt_id',
            'serial_number_id'
        );
    }
}
