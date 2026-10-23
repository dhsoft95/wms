<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockReference;
use App\Enums\CycleCountStatus;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\StockStatus;
use App\Enums\AdjustmentStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\CycleCount;
use App\Models\CycleCountLine;
use App\Models\Inventory\StockBalance;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\Foundation\DocumentNumber;
use App\Services\Foundation\UomConverter;
use Illuminate\Support\Facades\DB;

final class CycleCountWorkflow
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly AdjustmentWorkflow $adjustmentWorkflow,
    ) {}

    // Create a count and snapshot current system quantities for the chosen bins/items.
    public function create(array $data, User $user): CycleCount
    {
        return DB::transaction(function () use ($data, $user) {
            $count = CycleCount::create([
                'number'               => DocumentNumber::next('CNT'),
                'virtual_warehouse_id' => $data['virtual_warehouse_id'],
                'created_by'           => $user->id,
                'status'               => CycleCountStatus::Open,
                'notes'                => $data['notes'] ?? null,
            ]);

            // Build lines from the selected location+item+batch scope
            $balances = StockBalance::query()
                ->where('virtual_warehouse_id', $data['virtual_warehouse_id'])
                ->where('stock_status', StockStatus::Available->value)
                ->when(
                    ! empty($data['location_ids']),
                    fn ($q) => $q->whereIn('location_id', $data['location_ids'])
                )
                ->when(
                    ! empty($data['item_ids']),
                    fn ($q) => $q->whereIn('item_id', $data['item_ids'])
                )
                ->with('item')
                ->get();

            foreach ($balances as $balance) {
                // Default UoM: base UoM of the item
                $uomId = $balance->item->base_uom_id;

                CycleCountLine::create([
                    'cycle_count_id' => $count->id,
                    'location_id'    => $balance->location_id,
                    'item_id'        => $balance->item_id,
                    'batch_id'       => $balance->batch_id,
                    'uom_id'         => $uomId,
                    'system_qty'     => $balance->qty_on_hand,
                    'counted_qty'    => null,
                    'variance'       => null,
                ]);
            }

            activity()->performedOn($count)->causedBy($user)->log('Cycle count created');

            return $count;
        });
    }

    // Record a counter's entry for one line. system_qty is never returned to the counter (blind count).
    public function recordCount(CycleCountLine $line, string $countedQty, User $user): void
    {
        DB::transaction(function () use ($line, $countedQty, $user) {
            $count = $line->count;

            if (! in_array($count->status, [CycleCountStatus::Open, CycleCountStatus::Counting], true)) {
                throw new \DomainException('Count is not open for entry.');
            }

            // Advance to Counting on first entry
            if ($count->status === CycleCountStatus::Open) {
                $count->status = CycleCountStatus::Counting;
                $count->save();
            }

            $variance = bcsub($countedQty, (string) $line->system_qty, 4);

            $line->counted_qty = $countedQty;
            $line->variance    = $variance;
            $line->save();
        });
    }

    // Move to VarianceReview once all lines have been counted.
    public function submitForReview(CycleCount $count, User $user): CycleCount
    {
        return DB::transaction(function () use ($count, $user) {
            $count->load('lines');

            $uncounted = $count->lines->filter(fn ($l) => $l->counted_qty === null)->count();
            if ($uncounted > 0) {
                throw new \DomainException("{$uncounted} line(s) have not been counted yet.");
            }

            $count->status = $count->status->transitionTo(CycleCountStatus::VarianceReview);
            $count->save();

            activity()->performedOn($count)->causedBy($user)->log('Count submitted for variance review');

            return $count;
        });
    }

    // Post all variances as CountAdjustment movements.
    public function post(CycleCount $count, User $user): CycleCount
    {
        return DB::transaction(function () use ($count, $user) {
            if ($count->status === CycleCountStatus::Posted) {
                return $count;
            }

            if ($count->status !== CycleCountStatus::VarianceReview) {
                throw new InvalidTransitionException($count->status->value, CycleCountStatus::Posted->value);
            }

            $count->load('lines.item');

            $iKey = "cnt:{$count->id}:post";

            $ref = new StockReference(
                referenceType: 'cycle_count',
                referenceId:   $count->id,
                referenceNo:   $count->number,
            );

            foreach ($count->lines as $line) {
                if (bccomp((string) $line->variance, '0', 4) === 0) {
                    continue;
                }

                // Post variance as a CountAdjustment through the ledger directly
                $stockLine = new StockLine(
                    itemId:             $line->item_id,
                    locationId:         $line->location_id,
                    virtualWarehouseId: $count->virtual_warehouse_id,
                    stockStatus:        StockStatus::Available,
                    enteredQty:         (string) $line->variance,
                    enteredUomId:       $line->uom_id,
                    batchId:            $line->batch_id,
                );

                $this->ledger->post(
                    new \App\Data\Inventory\StockPosting(
                        MovementType::CountAdjustment,
                        $ref,
                        [$stockLine],
                        "{$iKey}:line:{$line->id}",
                    ),
                    $user,
                );
            }

            $count->status        = CycleCountStatus::Posted;
            $count->posted_at     = now();
            $count->approved_by   = $user->id;
            $count->idempotency_key = $iKey;
            $count->save();

            activity()->performedOn($count)->causedBy($user)->log('Cycle count posted');

            return $count;
        });
    }

    public function cancel(CycleCount $count, User $user): CycleCount
    {
        return DB::transaction(function () use ($count, $user) {
            $count = CycleCount::lockForUpdate()->findOrFail($count->id);

            if ($count->status->isTerminal()) {
                throw new \DomainException("Cycle count {$count->number} is already {$count->status->label()} and cannot be cancelled.");
            }

            $count->status       = CycleCountStatus::Cancelled;
            $count->cancelled_at = now();
            $count->cancelled_by = $user->id;
            $count->save();

            activity()->performedOn($count)->causedBy($user)->log('Cycle count cancelled');

            return $count;
        });
    }

    // Returns true if the given location is locked by any active cycle count.
    public function isLocationLocked(int $locationId): bool
    {
        return CycleCountLine::whereHas('count', fn ($q) => $q->active())
            ->where('location_id', $locationId)
            ->exists();
    }
}
