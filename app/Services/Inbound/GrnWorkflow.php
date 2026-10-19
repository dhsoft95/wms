<?php

declare(strict_types=1);

namespace App\Services\Inbound;

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockReference;
use App\Enums\GrnSource;
use App\Enums\GrnStatus;
use App\Enums\GrnVarianceType;
use App\Enums\Inbound\PurchaseOrderStatus;
use App\Enums\ReceiptCondition;
use App\Enums\Inventory\StockStatus;
use App\Enums\PutawayTaskStatus;
use App\Events\Inbound\GrnConfirmed;
use App\Events\Inbound\VarianceApprovalRequested;
use App\Exceptions\InvalidTransitionException;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\GrnReceipt;
use App\Models\GrnVarianceApproval;
use App\Models\Inventory\Batch;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PutawayTask;
use App\Models\User;
use App\Services\Foundation\DocumentNumber;
use App\Services\Inventory\StockLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class GrnWorkflow
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly ContainerWorkflow $containerWorkflow,
        private readonly PurchaseOrderWorkflow $poWorkflow,
    ) {}

    /**
     * Transition a GRN from Draft → Receiving.
     * If the GRN has a linked container, auto-confirm its arrival and park it
     * in the first free yard slot for the warehouse.
     */
    public function startReceiving(Grn $grn, User $user): void
    {
        DB::transaction(function () use ($grn, $user) {
            $this->transition($grn, GrnStatus::Receiving, $user);

            if ($grn->received_by === null) {
                $grn->received_by = $user->id;
                $grn->save();
            }

            // Auto-process the linked container
            if ($grn->container_id !== null) {
                $container = $grn->container;

                // Confirm arrival if still Expected
                if ($container && $container->status === \App\Enums\ContainerStatus::Expected) {
                    $this->containerWorkflow->confirmArrival(
                        $container,
                        now(),
                        $container->seal_no ?? '',
                        $user
                    );
                    $container->refresh();
                }

                // Auto-park in first free yard slot for this warehouse
                if ($container && $container->status === \App\Enums\ContainerStatus::Arrived) {
                    $occupiedIds = \App\Models\Container::whereNotNull('yard_location_id')
                        ->pluck('yard_location_id')
                        ->toArray();

                    $yardSlot = \App\Models\Location::where('type', 'yard_slot')
                        ->where('level', 'bin')
                        ->where('status', 'active')
                        ->where('warehouse_id', $grn->warehouse_id)
                        ->whereNotIn('id', $occupiedIds)
                        ->orderBy('code')
                        ->first();

                    if ($yardSlot) {
                        $this->containerWorkflow->park($container, $yardSlot, $user);
                    }
                }
            }

            activity()
                ->performedOn($grn)
                ->causedBy($user)
                ->log('Receiving started');
        });
    }

    /**
     * Called when all physical goods have been scanned/entered.
     * Evaluates whether variance approval is needed.
     */
    public function finishReceiving(Grn $grn, User $user): void
    {
        DB::transaction(function () use ($grn, $user) {
            if ($grn->needsVarianceApproval()) {
                $this->transition($grn, GrnStatus::PendingVarianceApproval, $user);

                if (! $grn->hasPendingApproval()) {
                    $approval = GrnVarianceApproval::create([
                        'grn_id'       => $grn->id,
                        'requested_by' => $user->id,
                    ]);

                    event(new VarianceApprovalRequested($grn, $approval));
                }

                activity()
                    ->performedOn($grn)
                    ->causedBy($user)
                    ->log('Receiving completed — awaiting variance approval');
            }
            // No else: when no variance, GRN stays Receiving and the user clicks Confirm GRN on the Show page.
        });
    }

    /**
     * Approve a variance and proceed to confirm.
     */
    public function approveVariance(Grn $grn, User $approver, string $notes = ''): void
    {
        DB::transaction(function () use ($grn, $approver, $notes) {
            $approval = $grn->varianceApprovals()->whereNull('decision')->firstOrFail();
            $approval->update([
                'decided_by' => $approver->id,
                'decision'   => 'approved',
                'notes'      => $notes,
            ]);

            // Now confirm
            $this->confirm($grn, $approver);
        });
    }

    /**
     * Reject a variance — sends GRN back to Receiving for correction.
     */
    public function rejectVariance(Grn $grn, User $decider, string $notes = ''): void
    {
        DB::transaction(function () use ($grn, $decider, $notes) {
            $approval = $grn->varianceApprovals()->whereNull('decision')->firstOrFail();
            $approval->update([
                'decided_by' => $decider->id,
                'decision'   => 'rejected',
                'notes'      => $notes,
            ]);

            $this->transition($grn, GrnStatus::Receiving, $decider);

            activity()
                ->performedOn($grn)
                ->causedBy($decider)
                ->log('Variance rejected — returned to receiving');
        });
    }

    /**
     * CRITICAL: All steps inside ONE DB::transaction with lockForUpdate.
     */
    public function confirm(Grn $grn, User $user): void
    {
        DB::transaction(function () use ($grn, $user) {
            // 1. Lock GRN row to prevent concurrent confirms
            $grn = Grn::where('id', $grn->id)->lockForUpdate()->firstOrFail();

            // 2. Idempotency check
            if ($grn->status === GrnStatus::Received) {
                return;
            }

            // 3. Re-check that we're in an appropriate state
            if (! in_array($grn->status, [GrnStatus::Receiving, GrnStatus::PendingVarianceApproval], true)) {
                throw new \DomainException("GRN {$grn->number} cannot be confirmed from status: {$grn->status->label()}");
            }

            // 3b. Guard: at least one receipt must exist
            if ($grn->receipts()->count() === 0) {
                throw new \DomainException("GRN {$grn->number} has no receipts. Enter received quantities before confirming.");
            }

            // Load dock + quarantine locations
            $dockLocation      = $grn->receivingDock;
            $warehouseDefault  = $grn->warehouse;

            if ($dockLocation === null) {
                $warehouseDefault->load('defaultReceivingDock', 'defaultQuarantineLocation');
                $dockLocation = $warehouseDefault->defaultReceivingDock;
            }

            if ($dockLocation === null) {
                throw new \DomainException("No dock location configured for GRN {$grn->number}. Set one on the GRN or the warehouse default.");
            }

            $quarantineLocation = $warehouseDefault->defaultQuarantineLocation
                ?? Location::where('warehouse_id', $grn->warehouse_id)
                    ->where('type', 'quarantine')
                    ->where('status', 'active')
                    ->first();

            $ref = new StockReference(
                referenceType: 'grn',
                referenceId:   $grn->id,
                referenceNo:   $grn->number,
            );

            $lines = $grn->lines()->with('receipts.uom', 'item', 'uom')->get();

            // 4. Post stock via StockLedger for each receipt
            foreach ($lines as $line) {
                foreach ($line->receipts as $receipt) {
                    $isGood     = $receipt->condition === ReceiptCondition::Good;
                    $locationId = $isGood
                        ? $dockLocation->id
                        : ($quarantineLocation?->id ?? $dockLocation->id);

                    $stockStatus = ($locationId === ($quarantineLocation?->id))
                        ? StockStatus::OnHold
                        : StockStatus::Available;

                    $stockLine = new StockLine(
                        itemId:             $line->item_id,
                        locationId:         $locationId,
                        virtualWarehouseId: $line->virtual_warehouse_id,
                        stockStatus:        $stockStatus,
                        enteredQty:         (string) $receipt->qty,
                        enteredUomId:       $receipt->uom_id,
                        batchNo:            $receipt->batch_no,
                        expiryDate:         $receipt->expiry_date ? Carbon::parse($receipt->expiry_date) : null,
                        lpnId:              $receipt->lpn_id,
                        serials:            $receipt->serial_nos ?? [],
                    );

                    $idempotencyKey = "grn:{$grn->id}:receipt:{$receipt->id}:confirm";

                    $this->ledger->receive($stockLine, $ref, $idempotencyKey);
                }
            }

            // 5. Create putaway tasks (one per receipt line)
            foreach ($lines as $line) {
                foreach ($line->receipts as $receipt) {
                    $isGood     = $receipt->condition === ReceiptCondition::Good;
                    $fromLocId  = $isGood
                        ? $dockLocation->id
                        : ($quarantineLocation?->id ?? $dockLocation->id);

                    // Find suggested location from item preferred zone or first available storage bin
                    $suggestedLocationId = $this->suggestPutawayLocation($line, $fromLocId);

                    // Resolve batch_id if batch_no set
                    $batchId = null;
                    if ($receipt->batch_no) {
                        $batch   = Batch::where('item_id', $line->item_id)
                            ->where('batch_no', $receipt->batch_no)
                            ->first();
                        $batchId = $batch?->id;
                    }

                    PutawayTask::create([
                        'grn_id'               => $grn->id,
                        'lpn_id'               => $receipt->lpn_id,
                        'item_id'              => $line->item_id,
                        'batch_id'             => $batchId,
                        'virtual_warehouse_id' => $line->virtual_warehouse_id,
                        'qty'                  => $receipt->qty,
                        'from_location_id'     => $fromLocId,
                        'suggested_location_id'=> $suggestedLocationId,
                        'status'               => PutawayTaskStatus::Open->value,
                    ]);
                }
            }

            // 6. Auto-unload container if all GRNs for it are now received
            if ($grn->container_id !== null) {
                $container = $grn->container;
                $grn->status = GrnStatus::Received;
                $grn->confirmed_by = $user->id;
                $grn->confirmed_at = now();
                $grn->save();

                $allReceived = Grn::where('container_id', $grn->container_id)
                    ->whereNotIn('status', [
                        GrnStatus::Received->value,
                        GrnStatus::PutawayInProgress->value,
                        GrnStatus::PutawayComplete->value,
                        GrnStatus::Closed->value,
                    ])
                    ->doesntExist();

                if ($allReceived && $container !== null) {
                    try {
                        $this->containerWorkflow->markUnloaded($container, $user);
                    } catch (\RuntimeException) {
                        // Gate may still block — that's OK, container remains unloading
                    }
                }
            } else {
                // 7. Set status = received
                $grn->status       = GrnStatus::Received;
                $grn->confirmed_by = $user->id;
                $grn->confirmed_at = now();
                $grn->save();
            }

            activity()
                ->performedOn($grn)
                ->causedBy($user)
                ->log('GRN confirmed — stock posted to dock');
        });

        // Fire AFTER commit (ShouldDispatchAfterCommit)
        event(new GrnConfirmed($grn->fresh()));
    }

    // ─── PO-driven receiving flow ─────────────────────────────────────────────

    /**
     * Creates a Draft GRN (number = null) as a receiving tally for a PO.
     * Dock is considered busy while this GRN is in Draft with number = null.
     */
    public function createDraftForPO(
        PurchaseOrder $po,
        int $dockLocationId,
        array $vehicleData,
        User $clerk,
    ): Grn {
        if (Grn::isDockBusy($dockLocationId)) {
            throw new \DomainException('Selected dock is currently busy. Choose a different dock.');
        }

        return DB::transaction(function () use ($po, $dockLocationId, $vehicleData, $clerk) {
            // For container POs, create the Container record now (at receiving time)
            $containerId = $po->container_id;
            if (
                $po->package_type?->value === 'container'
                && ! empty($vehicleData['container_no'])
                && $containerId === null
            ) {
                $container = \App\Models\Container::create([
                    'number'       => DocumentNumber::next('CTR'),
                    'container_no' => strtoupper($vehicleData['container_no']),
                    'size_type'    => $vehicleData['container_size_type'] ?? null,
                    'warehouse_id' => $po->warehouse_id,
                    'status'       => \App\Enums\ContainerStatus::Arrived,
                ]);
                $containerId = $container->id;
            }

            $grn = Grn::create([
                'number'                    => null, // assigned on completeReceiving()
                'source'                    => GrnSource::Supplier,
                'warehouse_id'              => $po->warehouse_id,
                'purchase_order_id'         => $po->id,
                'container_id'              => $containerId,
                'supplier_partner_id'       => $po->supplier_partner_id,
                'status'                    => GrnStatus::Draft,
                'received_by'               => $clerk->id,
                'receiving_dock_location_id' => $dockLocationId,
                'delivery_type'             => $vehicleData['delivery_type'] ?? null,
                'arrival_datetime'          => $vehicleData['arrival_datetime'] ?? now(),
                'vehicle_type'              => $vehicleData['vehicle_type'] ?? null,
                'vehicle_plate'             => $vehicleData['vehicle_plate'] ?? null,
                'driver_name'               => $vehicleData['driver_name'] ?? null,
                'driver_phone'              => $vehicleData['driver_phone'] ?? null,
                'driver_licence'            => $vehicleData['driver_licence'] ?? null,
                'transporter_name'          => $vehicleData['transporter_name'] ?? null,
                'seal_check_result'         => $vehicleData['seal_check_result'] ?? null,
                'seal_mismatch_reason'      => $vehicleData['seal_mismatch_reason'] ?? null,
            ]);

            // Copy PO lines → GRN expected lines
            $po->loadMissing('lines.item');
            foreach ($po->lines as $line) {
                $vwId = $po->virtual_warehouse_id ?? $line->item?->default_virtual_warehouse_id;
                if (! $vwId) {
                    continue;
                }

                GrnLine::create([
                    'grn_id'                  => $grn->id,
                    'purchase_order_line_id'  => $line->id,
                    'item_id'                 => $line->item_id,
                    'virtual_warehouse_id'    => $vwId,
                    'uom_id'                  => $line->uom_id,
                    'expected_qty'            => $line->qty,
                ]);
            }

            $this->poWorkflow->markReceivingInProgress($po, $clerk);

            activity()->performedOn($grn)->causedBy($clerk)
                ->log("Receiving tally created for PO {$po->number}");

            return $grn;
        });
    }

    /**
     * Completes a PO-driven receiving tally:
     *   1. Assign the GRN number (GRN-{PO.number}-{n})
     *   2. Post good stock via StockLedger (idempotent: po:{id}:receipt:{rid}:post)
     *   3. Create putaway tasks
     *   4. Advance GRN to Received
     *   5. Advance PO status (partial or pending putaway)
     *
     * Safe to retry — idempotency keys prevent double-posting.
     */
    public function completeReceiving(Grn $grn, User $user): void
    {
        if ($grn->purchase_order_id === null) {
            throw new \DomainException("completeReceiving() is only for PO-driven Draft GRNs.");
        }

        // Already completed — idempotent no-op
        if ($grn->status === GrnStatus::Received) {
            return;
        }

        if ($grn->status !== GrnStatus::Draft) {
            throw new \DomainException("completeReceiving() is only for PO-driven Draft GRNs.");
        }

        DB::transaction(function () use ($grn, $user) {
            // Re-lock the row
            $grn = Grn::where('id', $grn->id)->lockForUpdate()->firstOrFail();

            // Idempotency: already completed (re-check inside lock)
            if ($grn->status === GrnStatus::Received) {
                return;
            }

            if ($grn->receipts()->count() === 0) {
                throw new \DomainException('No receipt quantities entered. Record the tally before completing.');
            }

            // Assign GRN number: GRN-{PO.number}-{n}
            if ($grn->number === null) {
                $po          = PurchaseOrder::find($grn->purchase_order_id);
                $deliveryNo  = Grn::where('purchase_order_id', $po->id)
                    ->whereNotNull('number')
                    ->count() + 1;

                $grn->number          = "GRN-{$po->number}-{$deliveryNo}";
                $grn->grn_delivery_no = $deliveryNo;
                $grn->saveQuietly();
            }

            // Resolve dock + quarantine
            $dockLocation = $grn->receivingDock;
            if ($dockLocation === null) {
                throw new \DomainException("No dock location set on GRN {$grn->number}.");
            }

            $quarantineLocation = Location::where('warehouse_id', $grn->warehouse_id)
                ->where('type', 'quarantine')
                ->where('status', 'active')
                ->first();

            $ref   = new StockReference('grn', $grn->id, $grn->number);
            $lines = $grn->lines()->with('receipts.uom', 'item', 'uom')->get();

            // Auto-generate batch numbers where needed
            $this->autoGenerateBatches($lines);

            // Post stock (idempotent)
            foreach ($lines as $line) {
                foreach ($line->receipts as $receipt) {
                    $isGood     = $receipt->condition === ReceiptCondition::Good;
                    $locationId = $isGood
                        ? $dockLocation->id
                        : ($quarantineLocation?->id ?? $dockLocation->id);
                    $stockStatus = ($locationId === ($quarantineLocation?->id))
                        ? StockStatus::OnHold : StockStatus::Available;

                    $stockLine = new StockLine(
                        itemId:             $line->item_id,
                        locationId:         $locationId,
                        virtualWarehouseId: $line->virtual_warehouse_id,
                        stockStatus:        $stockStatus,
                        enteredQty:         (string) $receipt->qty,
                        enteredUomId:       $receipt->uom_id,
                        batchNo:            $receipt->batch_no,
                        expiryDate:         $receipt->expiry_date ? Carbon::parse($receipt->expiry_date) : null,
                        lpnId:              $receipt->lpn_id,
                        serials:            $receipt->serial_nos ?? [],
                    );

                    $idempotencyKey = "po:{$grn->purchase_order_id}:receipt:{$receipt->id}:post";
                    $this->ledger->receive($stockLine, $ref, $idempotencyKey);
                }
            }

            // Create putaway tasks
            foreach ($lines as $line) {
                foreach ($line->receipts as $receipt) {
                    $isGood    = $receipt->condition === ReceiptCondition::Good;
                    $fromLocId = $isGood
                        ? $dockLocation->id
                        : ($quarantineLocation?->id ?? $dockLocation->id);

                    $suggestedLocationId = $this->suggestPutawayLocation($line, $fromLocId);

                    $batchId = null;
                    if ($receipt->batch_no) {
                        $batch   = Batch::where('item_id', $line->item_id)
                            ->where('batch_no', $receipt->batch_no)->first();
                        $batchId = $batch?->id;
                    }

                    PutawayTask::create([
                        'grn_id'                => $grn->id,
                        'lpn_id'                => $receipt->lpn_id,
                        'item_id'               => $line->item_id,
                        'batch_id'              => $batchId,
                        'virtual_warehouse_id'  => $line->virtual_warehouse_id,
                        'qty'                   => $receipt->qty,
                        'from_location_id'      => $fromLocId,
                        'suggested_location_id' => $suggestedLocationId,
                        'status'                => PutawayTaskStatus::Open->value,
                    ]);
                }
            }

            // Advance GRN status
            $grn->status       = GrnStatus::Received;
            $grn->confirmed_by = $user->id;
            $grn->confirmed_at = now();
            $grn->save();

            // Advance PO status
            $po = PurchaseOrder::find($grn->purchase_order_id);
            if ($po) {
                $this->poWorkflow->onGrnConfirmed($po, $user);
            }

            activity()->performedOn($grn)->causedBy($user)
                ->log("Receiving completed — {$grn->number} — stock posted");
        });

        event(new GrnConfirmed($grn->fresh()));
    }

    /**
     * Auto-generates batch numbers (B-{yyyymmdd}-{seq}) for receipts on
     * batch-tracked items that have no batch_no entered.
     */
    private function autoGenerateBatches(\Illuminate\Support\Collection $lines): void
    {
        $seq = 1;
        $today = now()->format('Ymd');

        foreach ($lines as $line) {
            if (! $line->item?->track_batch) {
                continue;
            }

            foreach ($line->receipts as $receipt) {
                if ($receipt->batch_no === null || trim($receipt->batch_no) === '') {
                    $batchNo = "B-{$today}-" . str_pad((string) $seq++, 4, '0', STR_PAD_LEFT);
                    $receipt->batch_no = $batchNo;
                    $receipt->saveQuietly();
                }
            }
        }
    }

    public function cancel(Grn $grn, string $reason, User $user): void
    {
        DB::transaction(function () use ($grn, $reason, $user) {
            $this->transition($grn, GrnStatus::Cancelled, $user, $reason);
        });
    }

    public function close(Grn $grn, User $user): void
    {
        DB::transaction(function () use ($grn, $user) {
            $this->transition($grn, GrnStatus::Closed, $user);

            $grn->closed_at = now();
            $grn->save();
        });
    }

    /**
     * Re-post stock for an already-confirmed GRN with no stock transactions.
     * Uses the same idempotency keys as confirm(), so it is safe to call multiple times.
     * Returns the number of ledger calls made (0 means all were already posted).
     */
    public function postStock(Grn $grn): int
    {
        $count = 0;

        DB::transaction(function () use ($grn, &$count) {
            $dockLocation     = $grn->receivingDock;
            $warehouseDefault = $grn->warehouse;

            if ($dockLocation === null) {
                $warehouseDefault->load('defaultReceivingDock', 'defaultQuarantineLocation');
                $dockLocation = $warehouseDefault->defaultReceivingDock;
            }

            if ($dockLocation === null) {
                throw new \DomainException("No dock location configured for GRN {$grn->number}.");
            }

            $quarantineLocation = $warehouseDefault->defaultQuarantineLocation
                ?? Location::where('warehouse_id', $grn->warehouse_id)
                    ->where('type', 'quarantine')->where('status', 'active')->first();

            $ref   = new StockReference('grn', $grn->id, $grn->number);
            $lines = $grn->lines()->with('receipts.uom', 'item', 'uom')->get();

            foreach ($lines as $line) {
                foreach ($line->receipts as $receipt) {
                    $isGood      = $receipt->condition === ReceiptCondition::Good;
                    $locationId  = $isGood
                        ? $dockLocation->id
                        : ($quarantineLocation?->id ?? $dockLocation->id);
                    $stockStatus = ($locationId === ($quarantineLocation?->id))
                        ? StockStatus::OnHold : StockStatus::Available;

                    $stockLine = new StockLine(
                        itemId:             $line->item_id,
                        locationId:         $locationId,
                        virtualWarehouseId: $line->virtual_warehouse_id,
                        stockStatus:        $stockStatus,
                        enteredQty:         (string) $receipt->qty,
                        enteredUomId:       $receipt->uom_id,
                        batchNo:            $receipt->batch_no,
                        expiryDate:         $receipt->expiry_date ? Carbon::parse($receipt->expiry_date) : null,
                        lpnId:              $receipt->lpn_id,
                        serials:            $receipt->serial_nos ?? [],
                    );

                    $idempotencyKey = "grn:{$grn->id}:receipt:{$receipt->id}:confirm";
                    $this->ledger->receive($stockLine, $ref, $idempotencyKey);
                    $count++;
                }
            }
        });

        return $count;
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function transition(Grn $grn, GrnStatus $to, User $user, ?string $notes = null): void
    {
        $from = $grn->status;

        if (! $from->canTransitionTo($to)) {
            throw new InvalidTransitionException($from->value, $to->value);
        }

        $grn->status = $to;
        $grn->save();

        activity()
            ->performedOn($grn)
            ->causedBy($user)
            ->withProperties(['from' => $from->value, 'to' => $to->value, 'notes' => $notes])
            ->log("Status changed to {$to->label()}");
    }

    private function suggestPutawayLocation(GrnLine $line, int $fromLocationId): ?int
    {
        // Try item preferred zone first
        $item = $line->item;
        if ($item?->preferred_zone_id) {
            $bin = Location::where('parent_id', $item->preferred_zone_id)
                ->where('type', 'storage')
                ->where('status', 'active')
                ->first();
            if ($bin) {
                return $bin->id;
            }
        }

        // Fall back to any storage location in the same warehouse
        $warehouseId = Location::find($fromLocationId)?->warehouse_id;
        if ($warehouseId) {
            $bin = Location::where('warehouse_id', $warehouseId)
                ->where('type', 'storage')
                ->where('status', 'active')
                ->first();
            if ($bin) {
                return $bin->id;
            }
        }

        return null;
    }
}
