<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockReference;
use App\Enums\AdjustmentStatus;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\SerialStatus;
use App\Enums\Inventory\StockStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\ReasonCode;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\User;
use App\Services\Foundation\DocumentNumber;
use Illuminate\Support\Facades\DB;

final class AdjustmentWorkflow
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function create(array $data, User $user): StockAdjustment
    {
        return DB::transaction(function () use ($data, $user) {
            $adj = StockAdjustment::create([
                'number'               => DocumentNumber::next('ADJ'),
                'virtual_warehouse_id' => $data['virtual_warehouse_id'],
                'created_by'           => $user->id,
                'status'               => AdjustmentStatus::Draft,
                'notes'                => $data['notes'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                StockAdjustmentLine::create([
                    'stock_adjustment_id' => $adj->id,
                    'item_id'             => $line['item_id'],
                    'location_id'         => $line['location_id'],
                    'batch_id'            => $line['batch_id'] ?? null,
                    'lpn_id'              => $line['lpn_id'] ?? null,
                    'reason_code_id'      => $line['reason_code_id'],
                    'qty'                 => $line['qty'],
                    'uom_id'              => $line['uom_id'],
                    'serials'             => $line['serials'] ?? null,
                ]);
            }

            activity()->performedOn($adj)->causedBy($user)->log('Adjustment created');

            return $adj;
        });
    }

    // Submit: auto-posts if all line qty_abs <= threshold; otherwise awaits approval.
    public function submit(StockAdjustment $adj, User $user): StockAdjustment
    {
        return DB::transaction(function () use ($adj, $user) {
            $adj->status = $adj->status->transitionTo(AdjustmentStatus::Submitted);

            $threshold        = (float) Setting::get('adj_qty_threshold', 0);
            $exceedsThreshold = false;

            if ($threshold > 0) {
                $adj->load('lines');
                foreach ($adj->lines as $line) {
                    if (abs((float) $line->qty) > $threshold) {
                        $exceedsThreshold = true;
                        break;
                    }
                }
            } else {
                $exceedsThreshold = true;
            }

            $adj->threshold_exceeded = $exceedsThreshold;
            $adj->save();

            activity()->performedOn($adj)->causedBy($user)->log('Adjustment submitted');

            if (! $exceedsThreshold) {
                return $this->post($adj, $user);
            }

            return $adj;
        });
    }

    public function approve(StockAdjustment $adj, User $user): StockAdjustment
    {
        return DB::transaction(function () use ($adj, $user) {
            $adj->status      = $adj->status->transitionTo(AdjustmentStatus::Approved);
            $adj->approved_by = $user->id;
            $adj->save();

            activity()->performedOn($adj)->causedBy($user)->log('Adjustment approved');

            return $this->post($adj, $user);
        });
    }

    public function reject(StockAdjustment $adj, User $user, string $reason): StockAdjustment
    {
        return DB::transaction(function () use ($adj, $user, $reason) {
            $adj->status           = $adj->status->transitionTo(AdjustmentStatus::Rejected);
            $adj->rejection_reason = $reason;
            $adj->save();

            activity()->performedOn($adj)->causedBy($user)->log('Adjustment rejected: ' . $reason);

            return $adj;
        });
    }

    public function post(StockAdjustment $adj, User $user): StockAdjustment
    {
        return DB::transaction(function () use ($adj, $user) {
            if ($adj->idempotency_key !== null) {
                // Already posted — idempotent return
                if ($adj->status === AdjustmentStatus::Posted) {
                    return $adj;
                }
            }

            if ($adj->status !== AdjustmentStatus::Submitted && $adj->status !== AdjustmentStatus::Approved) {
                throw new InvalidTransitionException($adj->status->value, AdjustmentStatus::Posted->value);
            }

            $adj->load('lines.item', 'lines.uom');

            $iKey = "adj:{$adj->id}:post";

            $ref = new StockReference(
                referenceType: 'stock_adjustment',
                referenceId:   $adj->id,
                referenceNo:   $adj->number,
            );

            foreach ($adj->lines as $line) {
                $stockLine = new StockLine(
                    itemId:             $line->item_id,
                    locationId:         $line->location_id,
                    virtualWarehouseId: $adj->virtual_warehouse_id,
                    stockStatus:        StockStatus::Available,
                    enteredQty:         (string) $line->qty,
                    enteredUomId:       $line->uom_id,
                    batchId:            $line->batch_id,
                    lpnId:              $line->lpn_id,
                    serials:            $line->serials ?? [],
                );

                $this->ledger->adjust($stockLine, $ref, "{$iKey}:line:{$line->id}");
            }

            $adj->status        = AdjustmentStatus::Posted;
            $adj->posted_at     = now();
            $adj->idempotency_key = $iKey;
            $adj->save();

            activity()->performedOn($adj)->causedBy($user)->log('Adjustment posted');

            return $adj;
        });
    }
}
