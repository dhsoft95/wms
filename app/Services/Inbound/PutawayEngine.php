<?php

declare(strict_types=1);

namespace App\Services\Inbound;

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockReference;
use App\Enums\Inbound\PurchaseOrderStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\PutawayTaskStatus;
use App\Models\Grn;
use App\Models\Inventory\Lpn;
use App\Models\Inventory\SerialNumber;
use App\Models\Inventory\StockBalance;
use App\Models\PutawayTaskSplit;
use App\Models\Location;
use App\Models\PutawayTask;
use App\Models\User;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PutawayEngine
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly PurchaseOrderWorkflow $poWorkflow,
    ) {}

    /**
     * Suggest a destination location for a putaway task.
     */
    public function suggest(PutawayTask $task): ?Location
    {
        $task->load('item.preferredZone', 'fromLocation');

        // 1. Item has a preferred zone configured — search bins inside that zone
        $item = $task->item;
        if ($item?->preferred_zone_id) {
            $bin = Location::where('warehouse_id', $task->fromLocation?->warehouse_id)
                ->where('level', LocationLevel::Bin->value)
                ->where('type', LocationType::Storage->value)
                ->where('status', LocationStatus::Active->value)
                ->whereHas('parent', fn ($q) => $q->where('id', $item->preferred_zone_id)
                    ->orWhereHas('parent', fn ($q2) => $q2->where('id', $item->preferred_zone_id)))
                ->first();
            if ($bin) {
                return $bin;
            }
        }

        // 2. Any storage bin in same warehouse
        $warehouseId = $task->fromLocation?->warehouse_id;
        if ($warehouseId) {
            $bin = Location::where('warehouse_id', $warehouseId)
                ->where('level', LocationLevel::Bin->value)
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
        if (! $destination->isBin()) {
            throw new \DomainException(
                "Destination '{$destination->code}' is a {$destination->level->label()}, not a bin. Choose a bin location."
            );
        }

        // Hazardous item / location constraint
        $itemForCheck = \App\Models\Item::find($task->item_id);
        if ($itemForCheck !== null) {
            if ($itemForCheck->is_hazardous && $destination->type !== \App\Enums\LocationType::Hazardous) {
                throw new \DomainException("Hazardous item must be put away in a hazardous location.");
            }
            if (! $itemForCheck->is_hazardous && $destination->type === \App\Enums\LocationType::Hazardous) {
                throw new \DomainException("Non-hazardous item cannot be placed in a hazardous location.");
            }
        }

        if (app(\App\Services\Inventory\CycleCountWorkflow::class)->isLocationLocked($destination->id)) {
            throw new \DomainException(
                "Bin '{$destination->code}' is locked under an active cycle count and cannot receive stock."
            );
        }

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

            // For serial-tracked items, fetch the in-stock serials at the source location
            $serials = [];
            if ($item->track_serial) {
                $serialQuery = SerialNumber::where('item_id', $task->item_id)
                    ->where('location_id', $task->from_location_id)
                    ->where('status', \App\Enums\Inventory\SerialStatus::InStock);

                if ($task->batch_id) {
                    $serialQuery->where('batch_id', $task->batch_id);
                }
                if ($task->lpn_id) {
                    $serialQuery->where('lpn_id', $task->lpn_id);
                }

                $serials = $serialQuery->pluck('serial_no')->all();
            }

            $stockTx = $this->ledger->move(
                from:          $fromKey,
                toLocationId:  $destination->id,
                qty:           (string) $task->qty,
                uomId:         $uomId,
                ref:           $ref,
                serials:        $serials,
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

            // Check if GRN can advance to PutawayComplete and sync PO status
            $this->checkGrnPutawayProgress($task->grn, $user);

            activity()
                ->performedOn($task->grn)
                ->causedBy($user)
                ->withProperties(['task_id' => $task->id, 'location' => $destination->code])
                ->log('Putaway executed');

            return $task->fresh();
        });
    }

    /**
     * Execute a putaway task split across multiple bins.
     * $rows = [['location_id' => int, 'qty' => string, 'serials' => string[]], ...]
     */
    public function executeSplits(PutawayTask $task, array $rows, User $user): PutawayTask
    {
        if (empty($rows)) {
            throw new \DomainException('At least one bin row is required.');
        }

        // Pre-validate destinations outside the transaction
        $item = \App\Models\Item::find($task->item_id);
        foreach ($rows as $row) {
            $destination = Location::findOrFail($row['location_id']);
            if (! $destination->isBin()) {
                throw new \DomainException(
                    "Destination '{$destination->code}' is a {$destination->level->label()}, not a bin."
                );
            }
            if ($item !== null) {
                if ($item->is_hazardous && $destination->type !== \App\Enums\LocationType::Hazardous) {
                    throw new \DomainException("Hazardous item must be put away in a hazardous location.");
                }
                if (! $item->is_hazardous && $destination->type === \App\Enums\LocationType::Hazardous) {
                    throw new \DomainException("Non-hazardous item cannot be placed in a hazardous location.");
                }
            }
            if (app(\App\Services\Inventory\CycleCountWorkflow::class)->isLocationLocked($destination->id)) {
                throw new \DomainException(
                    "Bin '{$destination->code}' is locked under an active cycle count and cannot receive stock."
                );
            }
        }

        // Validate total qty equals task qty
        $totalQty = array_reduce($rows, fn ($carry, $r) => bcadd($carry, (string) $r['qty'], 4), '0.0000');
        if (bccomp($totalQty, (string) $task->qty, 4) !== 0) {
            throw new \DomainException(
                "Total split qty ({$totalQty}) must equal task qty ({$task->qty})."
            );
        }

        return DB::transaction(function () use ($task, $rows, $user) {
            $task = PutawayTask::where('id', $task->id)->lockForUpdate()->firstOrFail();

            if (! $task->isExecutable() && $task->status !== PutawayTaskStatus::Open) {
                throw new \DomainException("Task #{$task->id} cannot be executed from status: {$task->status->label()}");
            }

            $from        = $task->fromLocation;
            $item        = $task->item;
            $uomId       = $item->base_uom_id;
            $stockStatus = \App\Enums\Inventory\StockStatus::Available;
            if ($from?->type === LocationType::Quarantine) {
                $stockStatus = \App\Enums\Inventory\StockStatus::OnHold;
            }

            // For serial-tracked items, collect all user-specified serials to avoid re-fetching already-assigned ones
            $assignedSerials = [];

            $firstLocationId  = null;
            $firstTransaction = null;

            foreach ($rows as $i => $row) {
                $destination = Location::findOrFail($row['location_id']);
                $firstLocationId ??= $destination->id;

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

                $serials = $row['serials'] ?? [];

                // Auto-assign remaining in-stock serials when user didn't specify
                if ($item->track_serial && empty($serials)) {
                    $rowQtyInt   = (int) round((float) $row['qty']);
                    $serialQuery = SerialNumber::where('item_id', $task->item_id)
                        ->where('location_id', $task->from_location_id)
                        ->where('status', \App\Enums\Inventory\SerialStatus::InStock);
                    if ($task->batch_id) {
                        $serialQuery->where('batch_id', $task->batch_id);
                    }
                    if (! empty($assignedSerials)) {
                        $serialQuery->whereNotIn('serial_no', $assignedSerials);
                    }
                    $serials = $serialQuery->limit($rowQtyInt)->pluck('serial_no')->all();
                }

                $assignedSerials = array_merge($assignedSerials, $serials);

                $stockTx = $this->ledger->move(
                    from:           $fromKey,
                    toLocationId:   $destination->id,
                    qty:            (string) $row['qty'],
                    uomId:          $uomId,
                    ref:            $ref,
                    serials:        $serials,
                    idempotencyKey: "putaway:{$task->id}:split:{$i}",
                );

                $firstTransaction ??= $stockTx;

                PutawayTaskSplit::create([
                    'putaway_task_id'      => $task->id,
                    'location_id'          => $destination->id,
                    'qty'                  => $row['qty'],
                    'serial_nos'           => ! empty($serials) ? $serials : null,
                    'stock_transaction_id' => $stockTx->id,
                    'posted_by'            => $user->id,
                    'posted_at'            => now(),
                ]);
            }

            if ($task->lpn_id && $firstLocationId) {
                Lpn::where('id', $task->lpn_id)->update(['current_location_id' => $firstLocationId]);
            }

            $task->update([
                'actual_location_id'   => $firstLocationId,
                'status'               => PutawayTaskStatus::Completed->value,
                'completed_by'         => $user->id,
                'completed_at'         => now(),
                'stock_transaction_id' => $firstTransaction?->id,
            ]);

            $this->checkGrnPutawayProgress($task->grn, $user);

            activity()
                ->performedOn($task->grn)
                ->causedBy($user)
                ->withProperties(['task_id' => $task->id, 'splits' => count($rows)])
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

    private function checkGrnPutawayProgress(Grn $grn, User $user): void
    {
        $grn->load('putawayTasks');

        $inPutawayPhase = in_array($grn->status, [
            \App\Enums\GrnStatus::Received,
            \App\Enums\GrnStatus::PutawayInProgress,
        ], true);

        if (! $inPutawayPhase) {
            return;
        }

        $openTasks = $grn->putawayTasks->filter(
            fn (PutawayTask $t) => $t->status !== PutawayTaskStatus::Completed
                && $t->status !== PutawayTaskStatus::Cancelled
        );

        $grn->status = $openTasks->isEmpty()
            ? \App\Enums\GrnStatus::PutawayComplete
            : \App\Enums\GrnStatus::PutawayInProgress;

        $grn->save();

        // Sync parent PO status
        if ($grn->purchase_order_id === null) {
            return;
        }

        $po = $grn->purchaseOrder ?? $grn->load('purchaseOrder')->purchaseOrder;
        if ($po === null) {
            return;
        }

        // Advance PO from PendingPutaway → PutawayInProgress on first task execution
        if ($po->status === PurchaseOrderStatus::PendingPutaway) {
            $this->poWorkflow->markPutawayStarted($po, $user);
            $po->refresh();
        }

        // Advance PO to PendingSupervisorValidation when all GRNs for this PO are done
        if ($grn->status === \App\Enums\GrnStatus::PutawayComplete
            && $po->status === PurchaseOrderStatus::PutawayInProgress
        ) {
            $allDone = Grn::where('purchase_order_id', $po->id)
                ->whereNotIn('status', [
                    \App\Enums\GrnStatus::PutawayComplete->value,
                    \App\Enums\GrnStatus::Closed->value,
                ])
                ->doesntExist();

            if ($allDone) {
                $this->poWorkflow->markPutawayComplete($po, $user);
            }
        }
    }
}
