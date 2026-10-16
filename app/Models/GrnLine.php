<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GrnVarianceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrnLine extends Model
{
    protected $table = 'grn_lines';

    protected $fillable = [
        'grn_id',
        'container_expected_line_id',
        'item_id',
        'virtual_warehouse_id',
        'uom_id',
        'expected_qty',
        'received_good_qty',
        'received_damaged_qty',
        'variance_qty',
        'variance_type',
        'variance_reason_code_id',
        'remarks',
        'is_unexpected',
        'serials_missing',
        'serials_missing_qty',
    ];

    protected function casts(): array
    {
        return [
            'expected_qty'        => 'decimal:4',
            'received_good_qty'   => 'decimal:4',
            'received_damaged_qty'=> 'decimal:4',
            'variance_qty'        => 'decimal:4',
            'variance_type'        => GrnVarianceType::class,
            'is_unexpected'        => 'boolean',
            'serials_missing'      => 'boolean',
            'serials_missing_qty'  => 'decimal:4',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function grn(): BelongsTo
    {
        return $this->belongsTo(Grn::class);
    }

    public function containerExpectedLine(): BelongsTo
    {
        return $this->belongsTo(ContainerExpectedLine::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function virtualWarehouse(): BelongsTo
    {
        return $this->belongsTo(VirtualWarehouse::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function varianceReasonCode(): BelongsTo
    {
        return $this->belongsTo(ReasonCode::class, 'variance_reason_code_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GrnReceipt::class);
    }

    // ─── Business Methods ─────────────────────────────────────────────────────

    /**
     * Recalculate received totals and variance from the receipts table.
     * Saves the line without firing model events.
     */
    public function recalculateTotals(): void
    {
        $goodQty    = (string) $this->receipts()->where('condition', 'good')->sum('qty');
        $damagedQty = (string) $this->receipts()->where('condition', 'damaged')->sum('qty');

        $totalReceived = bcadd($goodQty, $damagedQty, 4);
        $expected      = (string) ($this->expected_qty ?? '0.0000');
        $varianceQty   = bcsub($totalReceived, $expected, 4);

        // Determine variance type
        $varianceType = GrnVarianceType::None;

        $hasShort   = bccomp($varianceQty, '0', 4) < 0;
        $hasExcess  = bccomp($varianceQty, '0', 4) > 0;
        $hasDamaged = bccomp($damagedQty, '0', 4) > 0;

        if ($hasDamaged && ($hasShort || $hasExcess)) {
            $varianceType = GrnVarianceType::Mixed;
        } elseif ($hasDamaged) {
            $varianceType = GrnVarianceType::Damaged;
        } elseif ($hasShort) {
            $varianceType = GrnVarianceType::Short;
        } elseif ($hasExcess) {
            $varianceType = GrnVarianceType::Excess;
        }

        $this->received_good_qty    = $goodQty;
        $this->received_damaged_qty = $damagedQty;
        $this->variance_qty         = $varianceQty;
        $this->variance_type        = $varianceType;

        // Track missing serials: receipts with null serial_nos on serial-tracked items
        if ($this->item?->track_serial) {
            $missingQty = (string) $this->receipts()->whereNull('serial_nos')->sum('qty');
            $this->serials_missing     = bccomp($missingQty, '0', 4) > 0;
            $this->serials_missing_qty = $missingQty;
        }

        $this->saveQuietly();
    }

    public function totalReceivedQty(): string
    {
        return bcadd((string) $this->received_good_qty, (string) $this->received_damaged_qty, 4);
    }
}
