<?php

declare(strict_types=1);

namespace App\Services\Inbound;

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockReference;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\PutawayTaskStatus;
use App\Models\Inventory\Lpn;
use App\Models\Inventory\StockBalance;
use App\Models\Location;
use App\Models\PutawayTask;
use App\Models\User;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PutawayEngine
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * Suggest a destination location for a putaway task.
     */
    public function suggest(PutawayTask $task): ?Location
    {
        $task->load('item.preferredZone', 'fromLocation');

        // 1. Item has a preferred zone configured
        $item = $task->item;
        if ($item?->preferred_zone_id) {
            $bin = Location::where('parent_id', $item->preferred_zone_id)
                ->where('type', LocationType::Storage->value)
                ->where('status', LocationStatus::Active->value)
                ->first();
            if ($bin) {
                return $bin;
            }
        }

        // 2. Any storage bin in same warehouse
        $warehouseId = $task->fromLocation?->warehouse_id;
        if ($warehouseId) {
            $bin = Location::where('warehouse_id', $warehouseId)
                ->where('type', LocationType::Storage->value)
                ->where('status', LocationStatus::Active->value)
                ->first();
            if ($bin) {
                return $bin;
            }
        }

        return null;
    }

    /**
     * Check if a location has capacity for the task.
     */
    public function checkCapacity(Location $location, PutawayTask $task): array
    {
        $stockQty = StockBalance::where('location_id', $location->id)
            ->sum('qty_on_hand');

        $maxPallets = $location->max_pallets ?? 0;
        $maxWeight  = $location->max_weight_kg ?? 0;

        return [
            'has_capacity' => true, // simplified — expand based on weight/pallet logic
            'current_qty'  => $stockQty,
            'max_pallets'  => $maxPallets,
            'max_weight'   => $maxWeight,
        ];
    }

    /**
     * Execute a single putaway task — moves stock from dock/quarantine to actual location.
     */
    public function execute(PutawayTask $task, Location $destination, User $user): PutawayTask
    {
        return DB::transaction(function () use ($task, $destination, $user) {
            $task = PutawayTask::where('id', $task->id)->lockForUpdate()->firstOrFail();

            if (! $task->isExecutable() && $task->status !== PutawayTaskStatus::Open) {
                throw new \DomainException("Task #{$task->id} cannot be executed from status: {$task->status->label()}");
            }

            $from = $task->fromLocation;

            $stockStatus = \App\Enums\Inventory\StockStatus::Available;
            if ($from?->type === LocationType::Quarantine) {
                $stockStatus = \App\Enums\Inventory\StockStatus::OnHold;
            }

            $fromKey = new StockKey(
                itemId:             $task->item_id,
                batchId:            $task->batch_id,
                lpnId:              $task->lpn_id,
                locationId:         $task->from_location_id,
                virtualWarehouseId: $task->virtual_warehouse_id,
                stockStatus:        $stockStatus,
            );

            $ref = new StockReference(
                referenceType: 'putaway_task',
                referenceId:   $task->id,
                referenceNo:   "PT-{$task->id}",
            );

            // Find base UOM for the item
            $item    = $task->item;
            $uomId   = $item->base_uom_id;

            $stockTx = $this->ledger->move(
                from:          $fromKey,
                toLocationId:  $destination->id,
                qty:           (string) $task->qty,
                uomId:         $uomId,
                ref:           $ref,
                idempotencyKey: "putaway:{$task->id}:execute",
            );

            $task->update([
                'actual_location_id'  => $destination->id,
                'status'              => PutawayTaskStatus::Completed->value,
                'completed_by'        => $user->id,
                'completed_at'        => now(),
                'stock_transaction_id'=> $stockTx->id,
            ]);

            // Update LPN location if present
            if ($task->lpn_id) {
                Lpn::where('id', $task->lpn_id)->update([
                    'current_location_id' => $destination->id,
                ]);
            }

            // Check if GRN can advance to PutawayComplete
            $this->checkGrnPutawayProgress($task->grn);

            activity()
                ->performedOn($task->grn)
                ->causedBy($user)
                ->withProperties(['task_id' => $task->id, 'location' => $destination->code])
                ->log('Putaway executed');

            return $task->fresh();
        });
    }

    /**
     * Bulk assign tasks to a user.
     */
    public function bulkAssign(Collection $tasks, User $assignee, User $assignedBy): int
    {
        return DB::transaction(function () use ($tasks, $assignee, $assignedBy) {
            $count = 0;
            foreach ($tasks as $task) {
                if ($task->isAssignable()) {
                    $task->update([
                        'assigned_to' => $assignee->id,
                        'status'      => PutawayTaskStatus::Assigned->value,
                    ]);
                    $count++;
                }
            }
            return $count;
        });
    }

    private function checkGrnPutawayProgress(\App\Models\Grn $grn): void
    {
        $grn->load('putawayTasks');

        $openTasks = $grn->putawayTasks->filter(
            fn (PutawayTask $t) => $t->status !== PutawayTaskStatus::Completed
                && $t->status !== PutawayTaskStatus::Cancelled
        );

        if ($openTasks->isEmpty() && $grn->status === \App\Enums\GrnStatus::PutawayInProgress) {
            $grn->status = \App\Enums\GrnStatus::PutawayComplete;
            $grn->save();
        } elseif ($grn->status === \App\Enums\GrnStatus::Received) {
            $grn->status = \App\Enums\GrnStatus::PutawayInProgress;
            $grn->save();
        }
    }
}
