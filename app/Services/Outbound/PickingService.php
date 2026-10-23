<?php

declare(strict_types=1);

namespace App\Services\Outbound;

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockPosting;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\StockStatus;
use App\Enums\Outbound\AllocationStatus;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\DoLineStatus;
use App\Enums\Outbound\PickListStatus;
use App\Enums\Outbound\PickTaskStatus;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderAllocation;
use App\Models\PickList;
use App\Models\PickTask;
use App\Models\StockCountRequest;
use App\Models\User;
use App\Services\Foundation\DocumentNumber;
use App\Services\Foundation\SettingsService;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\StockReservations;
use Illuminate\Support\Facades\DB;

final class PickingService
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly StockReservations $reservations,
        private readonly SettingsService $settings,
        private readonly AllocationService $allocation,
    ) {}

    public function generatePickList(DeliveryOrder $do, ?User $assignedTo = null): PickList
    {
        return DB::transaction(function () use ($do, $assignedTo) {
            // Lock the DO row to prevent concurrent generation
            DeliveryOrder::lockForUpdate()->findOrFail($do->id);

            $existing = PickList::where('delivery_order_id', $do->id)
                ->whereIn('status', [PickListStatus::Open->value, PickListStatus::InProgress->value])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new \DomainException(
                    "Pick list {$existing->number} is already {$existing->status->label()} for this order."
                );
            }

            // Only allocations that have no open pick task (short/picked tasks don't block re-generation)
            $allocations = $do->lines()
                ->with('allocations.location')
                ->get()
                ->flatMap(fn ($line) => $line->allocations->where('status', AllocationStatus::Reserved->value))
                ->filter(fn ($allocation) => ! PickTask::where('delivery_order_allocation_id', $allocation->id)
                    ->where('status', PickTaskStatus::Open->value)
                    ->exists()
                )
                ->sortBy([
                    fn ($a) => $a->location?->pick_sequence ?? PHP_INT_MAX,
                    fn ($a) => $a->location?->code ?? '',
                ])
                ->values();

            if ($allocations->isEmpty()) {
                $hasReserved = $do->lines()
                    ->with('allocations')
                    ->get()
                    ->flatMap(fn ($l) => $l->allocations->where('status', AllocationStatus::Reserved->value))
                    ->isNotEmpty();

                throw new \DomainException(
                    $hasReserved
                        ? 'Nothing to pick: all reserved allocations already have open pick tasks.'
                        : 'Nothing to pick: no reserved allocations on this order.'
                );
            }

            $pickList = PickList::create([
                'number'            => DocumentNumber::next('PCK'),
                'delivery_order_id' => $do->id,
                'status'            => PickListStatus::Open,
                'assigned_to'       => $assignedTo?->id,
            ]);

            $countLock = app(\App\Services\Inventory\CycleCountWorkflow::class);
            foreach ($allocations as $i => $allocation) {
                if ($countLock->isLocationLocked($allocation->location_id)) {
                    throw new \DomainException(
                        "Location ID {$allocation->location_id} is locked under an active cycle count and cannot be picked."
                    );
                }

                PickTask::create([
                    'pick_list_id'                 => $pickList->id,
                    'delivery_order_allocation_id' => $allocation->id,
                    'sequence'                     => $i + 1,
                    'location_id'                  => $allocation->location_id,
                    'item_id'                      => $allocation->item_id,
                    'batch_id'                     => $allocation->batch_id,
                    'lpn_id'                       => $allocation->lpn_id,
                    'qty_to_pick'                  => $allocation->qty,
                    'status'                       => PickTaskStatus::Open,
                ]);
            }

            return $pickList;
        });
    }

    public function cancelPickList(PickList $pickList): void
    {
        DB::transaction(function () use ($pickList) {
            $pickList = PickList::lockForUpdate()->findOrFail($pickList->id);

            if (! in_array($pickList->status, [PickListStatus::Open, PickListStatus::InProgress], true)) {
                throw new \DomainException(
                    "Pick list {$pickList->number} cannot be cancelled (status: {$pickList->status->label()})."
                );
            }

            $hasPicked = $pickList->tasks()
                ->whereIn('status', [PickTaskStatus::Picked->value, PickTaskStatus::Short->value])
                ->exists();

            if ($hasPicked) {
                throw new \DomainException(
                    "Cannot cancel pick list {$pickList->number}: some tasks have already been picked."
                );
            }

            $pickList->tasks()->where('status', PickTaskStatus::Open->value)
                ->update(['status' => PickTaskStatus::Cancelled->value]);

            $pickList->status = PickListStatus::Cancelled;
            $pickList->save();

            // If the DO moved to Picking but no active pick lists remain, revert to Allocated
            $do = $pickList->deliveryOrder;
            $hasActive = $do->pickLists()
                ->whereIn('status', [PickListStatus::Open->value, PickListStatus::InProgress->value])
                ->exists();

            if (! $hasActive && $do->status === DeliveryOrderStatus::Picking) {
                $do->status = DeliveryOrderStatus::Allocated;
                $do->save();
            }
        });
    }

    public function executeTask(
        PickTask $task,
        string $qty,
        ?User $picker,
        array $serials = [],
        ?string $device = null,
        ?string $requestId = null,
    ): PickTask {
        return DB::transaction(function () use ($task, $qty, $picker, $serials, $device, $requestId) {
            // Idempotency
            if ($requestId !== null) {
                $existing = \App\Models\Inventory\StockTransaction::where('idempotency_key', $requestId)->first();
                if ($existing !== null) {
                    $task->refresh();
                    return $task;
                }
            }

            $allocation = $task->allocation()->with('line.deliveryOrder')->firstOrFail();
            $do         = $allocation->line->deliveryOrder;
            $staging    = $do->warehouse->defaultStagingLocation;

            if ($staging === null) {
                throw new \DomainException('No default staging location configured for warehouse ' . $do->warehouse->code);
            }

            // Build Pick posting (MovementType::Pick, two lines)
            $posting = new StockPosting(
                type: MovementType::Pick,
                reference: new StockReference(
                    referenceType: 'pick_task',
                    referenceId:   $task->id,
                    referenceNo:   $task->pickList->number,
                ),
                lines: [
                    // Debit from bin (consumes reservation)
                    new StockLine(
                        itemId:             $task->item_id,
                        locationId:         $task->location_id,
                        virtualWarehouseId: $do->virtual_warehouse_id,
                        stockStatus:        StockStatus::Available,
                        enteredQty:         bcsub('0', $qty, 4),
                        enteredUomId:       $allocation->line->uom_id,
                        batchId:            $task->batch_id,
                        lpnId:              $task->lpn_id,
                        reservationId:      $allocation->stock_reservation_id,
                        serials:            $serials,
                    ),
                    // Credit to staging — serials follow to staging so their location stays accurate
                    new StockLine(
                        itemId:             $task->item_id,
                        locationId:         $staging->id,
                        virtualWarehouseId: $do->virtual_warehouse_id,
                        stockStatus:        StockStatus::Available,
                        enteredQty:         $qty,
                        enteredUomId:       $allocation->line->uom_id,
                        batchId:            $task->batch_id,
                        lpnId:              $task->lpn_id,
                        serials:            $serials,
                    ),
                ],
                idempotencyKey: $requestId,
                device:         $device ?? 'desktop',
            );

            $transaction = $this->ledger->post($posting, $picker);

            // Update task
            $task->qty_picked           = $qty;
            $task->status               = PickTaskStatus::Picked;
            $task->picked_by            = $picker?->id;
            $task->picked_at            = now();
            $task->stock_transaction_id = $transaction->id;
            $task->save();

            // Attach serials
            if (! empty($serials)) {
                $serialIds = \App\Models\Inventory\SerialNumber::where('item_id', $task->item_id)
                    ->whereIn('serial_no', $serials)
                    ->pluck('id');
                $task->serials()->syncWithoutDetaching($serialIds);
            }

            // Update allocation
            $allocation->status = AllocationStatus::Picked;
            $allocation->save();

            // Update line
            $line             = $allocation->line;
            $line->picked_qty = bcadd((string) $line->picked_qty, $qty, 4);
            $line->save();

            // Update pick list / DO status
            $this->syncPickListStatus($task->pickList->fresh());
            $this->syncDoStatus($do->fresh());

            return $task->fresh();
        });
    }

    public function unconfirmTask(PickTask $task, ?User $by): void
    {
        if ($task->status !== PickTaskStatus::Picked) {
            throw new \DomainException('Only picked tasks can be unconfirmed.');
        }

        $allocation = $task->allocation()->with('line.deliveryOrder', 'stockBalance')->firstOrFail();
        $do         = $allocation->line->deliveryOrder;

        if ($do->status === DeliveryOrderStatus::Dispatched || $do->status === DeliveryOrderStatus::Delivered) {
            throw new \DomainException('Cannot unconfirm a task on a dispatched or delivered order.');
        }

        DB::transaction(function () use ($task, $allocation, $do, $by) {
            // Reverse the pick transaction
            if ($task->stockTransaction) {
                $this->ledger->reverse($task->stockTransaction, 'Pick task unconfirmed');
            }

            // Re-create a reservation for this allocation
            $balance  = $allocation->stockBalance;
            $newReservation = $this->reservations->reserve(
                $balance,
                (string) $task->qty_picked,
                'delivery_order_allocation',
                $allocation->id,
            );

            // Update allocation
            $allocation->status               = AllocationStatus::Reserved;
            $allocation->stock_reservation_id = $newReservation->id;
            $allocation->save();

            // Update line picked_qty
            $line             = $allocation->line;
            $line->picked_qty = bcsub((string) $line->picked_qty, (string) $task->qty_picked, 4);
            if (bccomp($line->picked_qty, '0', 4) < 0) {
                $line->picked_qty = '0.0000';
            }
            $line->save();

            // Detach serials
            $task->serials()->detach();

            // Reset task
            $task->status               = PickTaskStatus::Open;
            $task->qty_picked           = '0.0000';
            $task->picked_by            = null;
            $task->picked_at            = null;
            $task->stock_transaction_id = null;
            $task->save();

            $this->syncPickListStatus($task->pickList->fresh());
            $this->syncDoStatus($do->fresh());
        });
    }

    public function handleShort(PickTask $task, string $shortQty, int $reasonCodeId, ?User $picker): void
    {
        DB::transaction(function () use ($task, $shortQty, $reasonCodeId, $picker) {
            $allocation = $task->allocation()->with('line.deliveryOrder', 'stockReservation')->firstOrFail();

            $task->qty_short            = $shortQty;
            $task->status               = PickTaskStatus::Short;
            $task->short_reason_code_id = $reasonCodeId;
            $task->picked_by            = $picker?->id;
            $task->picked_at            = now();
            $task->save();

            // Release the short portion of the reservation
            if ($allocation->stockReservation) {
                $this->reservations->release($allocation->stockReservation, $shortQty);
            }

            // Update line
            $line            = $allocation->line;
            $line->short_qty = bcadd((string) $line->short_qty, $shortQty, 4);
            $line->save();

            // Create stock count request
            StockCountRequest::create([
                'location_id'    => $task->location_id,
                'item_id'        => $task->item_id,
                'reason'         => 'short_pick',
                'reference_type' => 'pick_task',
                'reference_id'   => $task->id,
                'requested_by'   => $picker?->id ?? $allocation->line->deliveryOrder->requested_by,
                'status'         => 'open',
            ]);

            // Reallocate if configured
            $behaviour = $this->settings->get('outbound.short_pick_behaviour', 'leave_short');
            if ($behaviour === 'reallocate') {
                $this->allocation->allocateLine($line, $allocation->line->deliveryOrder);

                // Add new pick tasks for any new allocations
                $newAllocations = $line->allocations()
                    ->where('status', AllocationStatus::Reserved->value)
                    ->whereDoesntHave('pickTask', fn ($q) => $q->whereNotIn('status', [PickTaskStatus::Cancelled->value]))
                    ->with('location')
                    ->get()
                    ->sortBy([
                        fn ($a) => $a->location?->pick_sequence ?? PHP_INT_MAX,
                        fn ($a) => $a->location?->code ?? '',
                    ]);

                $lastSeq = $task->pickList->tasks()->max('sequence') ?? 0;
                foreach ($newAllocations as $newAllocation) {
                    $lastSeq++;
                    PickTask::create([
                        'pick_list_id'                 => $task->pick_list_id,
                        'delivery_order_allocation_id' => $newAllocation->id,
                        'sequence'                     => $lastSeq,
                        'location_id'                  => $newAllocation->location_id,
                        'item_id'                      => $newAllocation->item_id,
                        'batch_id'                     => $newAllocation->batch_id,
                        'lpn_id'                       => $newAllocation->lpn_id,
                        'qty_to_pick'                  => $newAllocation->qty,
                        'status'                       => PickTaskStatus::Open,
                    ]);
                }
            }

            $this->syncDoStatus($allocation->line->deliveryOrder->fresh());
        });
    }

    private function syncPickListStatus(PickList $pickList): void
    {
        $tasks       = $pickList->tasks()->get();
        $allDone     = $tasks->every(fn ($t) => in_array($t->status, [PickTaskStatus::Picked, PickTaskStatus::Short, PickTaskStatus::Cancelled], true));
        $anyProgress = $tasks->some(fn ($t) => $t->status->value !== 'open');

        if ($allDone) {
            $pickList->status       = PickListStatus::Completed;
            $pickList->started_at ??= now();
            $pickList->completed_at = now();
        } elseif ($anyProgress) {
            $pickList->status     = PickListStatus::InProgress;
            $pickList->started_at ??= now();
        }
        $pickList->save();
    }

    private function syncDoStatus(DeliveryOrder $do): void
    {
        $tasks = $do->pickLists()->with('tasks')->get()->flatMap(fn ($pl) => $pl->tasks);
        if ($tasks->isEmpty()) {
            return;
        }

        $allDone     = $tasks->every(fn ($t) => in_array($t->status->value, ['picked', 'short', 'cancelled'], true));
        $anyProgress = $tasks->some(fn ($t) => $t->status->value !== 'open');

        if ($allDone) {
            $do->status = DeliveryOrderStatus::Picked;
        } elseif ($anyProgress && $do->status === DeliveryOrderStatus::Allocated) {
            $do->status = DeliveryOrderStatus::Picking;
        }
        $do->save();
    }
}
