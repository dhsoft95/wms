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
use App\Enums\Outbound\GatePassStatus;
use App\Enums\Outbound\PickTaskStatus;
use App\Models\Dispatch;
use App\Models\DispatchItem;
use App\Models\GatePass;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderLine;
use App\Models\User;
use App\Services\Foundation\DocumentNumber;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

final class DispatchService
{
    public function __construct(
        private readonly StockLedger $ledger,
    ) {}

    public function confirmDispatch(
        DeliveryOrder $do,
        array $data,
        User $by,
        bool $createBackorder = false,
        ?string $requestId = null
    ): Dispatch {
        // Default idempotency key
        $idempotencyKey = $requestId ?? "do:{$do->id}:dispatch";

        return DB::transaction(function () use ($do, $data, $by, $idempotencyKey, $createBackorder) {
            // Idempotency check
            $existingTx = \App\Models\Inventory\StockTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existingTx !== null) {
                return Dispatch::where('stock_transaction_id', $existingTx->id)->firstOrFail();
            }

            if ($do->status !== DeliveryOrderStatus::Picked) {
                throw new \DomainException('Can only dispatch a picked delivery order.');
            }

            // Guard: at least something must have been picked across all lines
            $totalPicked = $do->lines->sum(fn ($l) => (float) $l->picked_qty);
            if ($totalPicked <= 0) {
                throw new \DomainException('Nothing has been picked on this order. Dispatch blocked.');
            }

            $staging = $do->warehouse->defaultStagingLocation;
            if ($staging === null) {
                throw new \DomainException('No default staging location configured.');
            }

            // Create dispatch record
            $dispatch = Dispatch::create([
                'number'                 => DocumentNumber::next('DSP'),
                'delivery_order_id'      => $do->id,
                'vehicle_reg_no'         => $data['vehicle_reg_no'],
                'container_no'           => $data['container_no'] ?? null,
                'container_size'         => $data['container_size'] ?? null,
                'driver_name'            => $data['driver_name'],
                'driver_phone'           => $data['driver_phone'],
                'driver_id_no'           => $data['driver_id_no'] ?? null,
                'transporter_partner_id' => $data['transporter_partner_id'] ?? null,
                'seal_no'                => $data['seal_no'] ?? null,
                'notes'                  => $data['notes'] ?? null,
                'dispatched_by'          => $by->id,
                'dispatched_at'          => now(),
                'dn_number'              => \App\Services\Foundation\DocumentNumber::next('DN'),
                'rcn_number'             => \App\Services\Foundation\DocumentNumber::next('RCN'),
            ]);

            // Gather picked tasks to build dispatch lines
            $pickedTasks = $do->pickLists()
                ->with('tasks.allocation.line')
                ->get()
                ->flatMap(fn ($pl) => $pl->tasks->where('status', PickTaskStatus::Picked->value));

            // Build dispatch items and StockLines
            $stockLines = [];
            foreach ($pickedTasks as $task) {
                DispatchItem::create([
                    'dispatch_id' => $dispatch->id,
                    'lpn_id'      => $task->lpn_id,
                    'item_id'     => $task->item_id,
                    'batch_id'    => $task->batch_id,
                    'qty'         => $task->qty_picked,
                    'is_scanned'  => false,
                ]);

                // Serials from pick_task_serials
                $serials = $task->serials()->pluck('serial_no')->toArray();

                $stockLines[] = new StockLine(
                    itemId:             $task->item_id,
                    locationId:         $staging->id,
                    virtualWarehouseId: $do->virtual_warehouse_id,
                    stockStatus:        StockStatus::Available,
                    enteredQty:         bcsub('0', (string) $task->qty_picked, 4),
                    enteredUomId:       $task->allocation->line->uom_id,
                    batchId:            $task->batch_id,
                    lpnId:              $task->lpn_id,
                    serials:            $serials,
                );
            }

            // Post dispatch to ledger
            $posting = new StockPosting(
                type: MovementType::Dispatch,
                reference: new StockReference(
                    referenceType: 'dispatch',
                    referenceId:   $dispatch->id,
                    referenceNo:   $dispatch->number,
                ),
                lines:          $stockLines,
                idempotencyKey: $idempotencyKey,
                device:         $data['device'] ?? 'desktop',
            );

            $transaction = $this->ledger->post($posting, $by);

            $dispatch->stock_transaction_id = $transaction->id;
            $dispatch->save();

            // Update DO lines dispatched_qty
            foreach ($do->lines()->with('allocations')->get() as $line) {
                $dispatchedQty = $line->allocations()
                    ->where('status', AllocationStatus::Picked->value)
                    ->sum('qty');

                $line->dispatched_qty = $dispatchedQty;
                $line->status         = DoLineStatus::Dispatched;
                $line->save();
            }

            // Transition DO
            $do->status = DeliveryOrderStatus::Dispatched;
            $do->save();

            // Create Gate Pass
            GatePass::create([
                'number'      => DocumentNumber::next('GP'),
                'dispatch_id' => $dispatch->id,
                'status'      => GatePassStatus::Issued,
            ]);

            activity()->performedOn($do)->causedBy($by)->log("Dispatched as {$dispatch->number}");

            if ($createBackorder) {
                $this->maybeCreateBackorder($do, $by);
            }

            return $dispatch;
        });
    }

    private function maybeCreateBackorder(DeliveryOrder $do, User $by): void
    {
        $shortLines = $do->lines()->get()->filter(function ($line) {
            return bccomp(
                bcsub((string) $line->requested_qty_base, (string) $line->dispatched_qty, 4),
                '0', 4
            ) > 0;
        });

        if ($shortLines->isEmpty()) {
            return;
        }

        $backorder = DeliveryOrder::create([
            'number'               => DocumentNumber::next('DO'),
            'virtual_warehouse_id' => $do->virtual_warehouse_id,
            'warehouse_id'         => $do->warehouse_id,
            'department_id'        => $do->department_id,
            'consignee_partner_id' => $do->consignee_partner_id,
            'delivery_address'     => $do->delivery_address,
            'required_date'        => $do->required_date,
            'priority'             => $do->priority,
            'allocation_rule'      => $do->allocation_rule,
            'status'               => DeliveryOrderStatus::Approved,
            'requested_by'         => $do->requested_by,
            'remarks'              => "Backorder of {$do->number}",
            'backorder_of_id'      => $do->id,
        ]);

        foreach ($shortLines as $line) {
            $remainder = bcsub((string) $line->requested_qty_base, (string) $line->dispatched_qty, 4);
            DeliveryOrderLine::create([
                'delivery_order_id'  => $backorder->id,
                'line_no'            => $line->line_no,
                'item_id'            => $line->item_id,
                'uom_id'             => $line->uom_id,
                'requested_qty'      => $remainder,
                'requested_qty_base' => $remainder,
                'requested_batch_id' => $line->requested_batch_id,
                'status'             => DoLineStatus::Pending,
            ]);
        }

        activity()->performedOn($do)->causedBy($by)
            ->log("Backorder {$backorder->number} created for {$shortLines->count()} short line(s).");
    }

    public function verifyGateExit(GatePass $gatePass, User $by): GatePass
    {
        if ($gatePass->status === GatePassStatus::Exited) {
            throw new \DomainException('Gate pass has already been exited.');
        }

        $gatePass->update([
            'status'      => GatePassStatus::Exited,
            'exited_at'   => now(),
            'verified_by' => $by->id,
        ]);

        activity()->performedOn($gatePass)->causedBy($by)->log('Gate exit verified');

        return $gatePass->fresh();
    }
}
