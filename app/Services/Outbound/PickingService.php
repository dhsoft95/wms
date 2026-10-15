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
            $pickList = PickList::create([
                'number'            => DocumentNumber::next('PCK'),
                'delivery_order_id' => $do->id,
                'status'            => PickListStatus::Open,
                'assigned_to'       => $assignedTo?->id,
            ]);

            $allocations = $do->lines()
                ->with('allocations.location')
                ->get()
                ->flatMap(fn ($line) => $line->allocations->where('status', AllocationStatus::Reserved->value))
                ->sortBy([
                    fn ($a) => $a->location?->pick_sequence ?? PHP_INT_MAX,
                    fn ($a) => $a->location?->code ?? '',
                ])
                ->values();

            foreach ($allocations as $i => $allocation) {
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
                    // Credit to staging
                    new StockLine(
                        itemId:             $task->item_id,
                        locationId:         $staging->id,
                        virtualWarehouseId: $do->virtual_warehouse_id,
                        stockStatus:        StockStatus::Available,
                        enteredQty:         $qty,
                        enteredUomId:       $allocation->line->uom_id,
                        batchId:            $task->batch_id,
                        lpnId:              $task->lpn_id,
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
                    ->whereDoesntHave('pickTask')
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
