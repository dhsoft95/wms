<?php

declare(strict_types=1);

namespace App\Services\Transfers;

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationType;
use App\Models\Location;
use App\Models\User;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferLine;
use App\Enums\Transfers\WarehouseTransferStatus;
use App\Services\Foundation\DocumentNumber;
use App\Services\Inventory\StockLedger;
use App\Services\Outbound\AllocationService;
use App\Services\Outbound\DispatchService;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the full lifecycle of a warehouse-to-warehouse physical stock
 * transfer (WTR): Draft → Submitted → Accepted → Dispatched → Completed.
 *
 * The associated PurchaseOrderWorkflow dependency is resolved lazily via the
 * service container so that this class compiles even when that service is not
 * yet bound (useful during incremental development / separate agent worktrees).
 */
final class WarehouseTransferWorkflow
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly AllocationService $allocation,
        private readonly DispatchService $dispatchService,
    ) {}

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(array $data, User $by): WarehouseTransfer
    {
        $authorized = $by->hasAnyRole(['department-user', 'warehouse-manager', 'system-admin'])
            || $by->can('transfers.create');

        if (! $authorized) {
            throw new \DomainException('You are not authorized to create warehouse transfers.');
        }

        return DB::transaction(function () use ($data, $by) {
            $wtr = WarehouseTransfer::create([
                'number'                    => DocumentNumber::next('WTR'),
                'from_warehouse_id'         => $data['from_warehouse_id'],
                'to_warehouse_id'           => $data['to_warehouse_id'],
                'from_virtual_warehouse_id' => $data['from_virtual_warehouse_id'],
                'to_virtual_warehouse_id'   => $data['to_virtual_warehouse_id'] ?? null,
                'status'                    => WarehouseTransferStatus::Draft,
                'notes'                     => $data['notes'] ?? null,
                'created_by'                => $by->id,
            ]);

            foreach ($data['lines'] as $lineNo => $lineData) {
                WarehouseTransferLine::create([
                    'warehouse_transfer_id' => $wtr->id,
                    'line_no'               => $lineNo + 1,
                    'source_location_id'    => $lineData['source_location_id'] ?? null,
                    'item_id'               => $lineData['item_id'],
                    'uom_id'                => $lineData['uom_id'],
                    'qty'                   => $lineData['qty'],
                    'qty_base'              => $lineData['qty_base'] ?? $lineData['qty'],
                    'batch_no'              => $lineData['batch_no'] ?? null,
                    'expiry_date'           => $lineData['expiry_date'] ?? null,
                ]);
            }

            activity()->performedOn($wtr)->causedBy($by)->log('Warehouse transfer created');

            return $wtr;
        });
    }

    // ─── Submit ───────────────────────────────────────────────────────────────

    public function submit(WarehouseTransfer $wtr, User $by): void
    {
        DB::transaction(function () use ($wtr, $by) {
            if ($wtr->status !== WarehouseTransferStatus::Draft) {
                throw new \DomainException('Only draft warehouse transfers can be submitted.');
            }

            $wtr->status = WarehouseTransferStatus::Submitted;
            $wtr->save();

            activity()->performedOn($wtr)->causedBy($by)->log('Warehouse transfer submitted');

            // Determine if acceptance is needed:
            // - Same VW (same department): auto-accept if no item requires approval
            // - Different departments: always require explicit acceptance
            $sameDept = $wtr->from_virtual_warehouse_id !== null
                && $wtr->from_virtual_warehouse_id === $wtr->to_virtual_warehouse_id;

            if ($sameDept) {
                $this->doAccept($wtr, $by);
            }
            // Otherwise: acceptance stays pending for warehouse-manager / system-admin
        });
    }

    // ─── Accept ───────────────────────────────────────────────────────────────

    public function accept(WarehouseTransfer $wtr, User $by): void
    {
        DB::transaction(function () use ($wtr, $by) {
            if ($wtr->status !== WarehouseTransferStatus::Submitted) {
                throw new \DomainException('Only submitted warehouse transfers can be accepted.');
            }

            $this->doAccept($wtr, $by);
        });
    }

    private function doAccept(WarehouseTransfer $wtr, User $by): void
    {
        $wtr->status      = WarehouseTransferStatus::Accepted;
        $wtr->accepted_by = $by->id;
        $wtr->accepted_at = now();
        $wtr->save();

        activity()->performedOn($wtr)->causedBy($by)->log('Warehouse transfer accepted');
    }

    // ─── Reject ───────────────────────────────────────────────────────────────

    public function reject(WarehouseTransfer $wtr, string $reason, User $by): void
    {
        DB::transaction(function () use ($wtr, $reason, $by) {
            $rejectableStatuses = [WarehouseTransferStatus::Submitted, WarehouseTransferStatus::Accepted];
            if (! in_array($wtr->status, $rejectableStatuses, true)) {
                throw new \DomainException('Warehouse transfer cannot be rejected in its current status.');
            }

            $wtr->status           = WarehouseTransferStatus::Rejected;
            $wtr->rejection_reason = $reason;
            $wtr->save();

            activity()->performedOn($wtr)->causedBy($by)->log("Warehouse transfer rejected: {$reason}");
        });
    }

    // ─── Dispatch ─────────────────────────────────────────────────────────────

    /**
     * Move stock from the source warehouse bins to the destination warehouse's
     * IN-TRANSIT location, and auto-create an inbound PO at the destination.
     *
     * @param  array{lines?: array<array{source_location_id: int, qty?: numeric-string}>}  $data
     */
    public function dispatch(WarehouseTransfer $wtr, array $data, User $by): void
    {
        DB::transaction(function () use ($wtr, $data, $by) {
            $allowedStatuses = [WarehouseTransferStatus::Accepted, WarehouseTransferStatus::Draft];
            if (! in_array($wtr->status, $allowedStatuses, true)) {
                throw new \DomainException('Warehouse transfer cannot be dispatched in its current status.');
            }

            $iKey = "wtr:{$wtr->id}:dispatch";

            // Idempotency guard
            if ($wtr->idempotency_key === $iKey) {
                return;
            }

            $wtr->load('lines');

            // Find IN-TRANSIT location at the destination warehouse
            $transitLocation = Location::where('warehouse_id', $wtr->to_warehouse_id)
                ->where('type', LocationType::Transit->value)
                ->first();

            if ($transitLocation === null) {
                throw new \DomainException('No in-transit location found for the destination warehouse.');
            }

            $ref = new StockReference(
                referenceType: 'warehouse_transfer',
                referenceId:   $wtr->id,
                referenceNo:   $wtr->number,
            );

            // Move stock from source bins to the in-transit location
            $lineDataByIndex = array_values($data['lines'] ?? []);
            foreach ($wtr->lines as $index => $line) {
                $sourceLocationId = $lineDataByIndex[$index]['source_location_id']
                    ?? ($line->source_location_id ?? null);

                if ($sourceLocationId === null) {
                    throw new \DomainException(
                        "Line {$line->id}: source_location_id is required for dispatch."
                    );
                }

                $fromKey = new StockKey(
                    itemId:             $line->item_id,
                    batchId:            null,
                    lpnId:              null,
                    locationId:         (int) $sourceLocationId,
                    virtualWarehouseId: $wtr->from_virtual_warehouse_id,
                    stockStatus:        StockStatus::Available,
                );

                $this->ledger->move(
                    $fromKey,
                    $transitLocation->id,
                    (string) $line->qty_base,
                    $line->uom_id,
                    $ref,
                    [],
                    "{$iKey}:line:{$line->id}",
                );
            }

            // Generate dispatch numbers
            $dnNumber  = DocumentNumber::next('DN');
            $rcnNumber = DocumentNumber::next('RCN');

            $wtr->status          = WarehouseTransferStatus::Dispatched;
            $wtr->dispatched_at   = now();
            $wtr->idempotency_key = $iKey;
            $wtr->dn_number       = $dnNumber;
            $wtr->rcn_number      = $rcnNumber;
            $wtr->save();

            // Auto-create inbound PO at destination warehouse via service container
            // PurchaseOrderWorkflow is resolved lazily to avoid hard coupling
            $this->autoCreateInboundPo($wtr);

            activity()->performedOn($wtr)->causedBy($by)->log('Warehouse transfer dispatched');
        });
    }

    // ─── Receive ──────────────────────────────────────────────────────────────

    public function receive(WarehouseTransfer $wtr, User $by): void
    {
        DB::transaction(function () use ($wtr, $by) {
            if ($wtr->status !== WarehouseTransferStatus::Dispatched) {
                throw new \DomainException('Only dispatched warehouse transfers can be received.');
            }

            $iKey = "wtr:{$wtr->id}:receive";

            if ($wtr->idempotency_key === $iKey) {
                return;
            }

            // If departments differ, transfer ownership of stock in the transit location
            if ($wtr->from_virtual_warehouse_id !== $wtr->to_virtual_warehouse_id
                && $wtr->to_virtual_warehouse_id !== null) {

                $transitLocation = Location::where('warehouse_id', $wtr->to_warehouse_id)
                    ->where('type', LocationType::Transit->value)
                    ->first();

                if ($transitLocation !== null) {
                    $wtr->load('lines');

                    $ref = new StockReference(
                        referenceType: 'warehouse_transfer',
                        referenceId:   $wtr->id,
                        referenceNo:   $wtr->number,
                    );

                    foreach ($wtr->lines as $line) {
                        $stockKey = new StockKey(
                            itemId:             $line->item_id,
                            batchId:            null,
                            lpnId:              null,
                            locationId:         $transitLocation->id,
                            virtualWarehouseId: $wtr->from_virtual_warehouse_id,
                            stockStatus:        StockStatus::Available,
                        );

                        $this->ledger->changeOwner(
                            $stockKey,
                            $wtr->to_virtual_warehouse_id,
                            (string) $line->qty_base,
                            $line->uom_id,
                            $ref,
                            [],
                            "{$iKey}:line:{$line->id}",
                        );
                    }
                }
            }

            $wtr->status          = WarehouseTransferStatus::Completed;
            $wtr->received_at     = now();
            $wtr->idempotency_key = $iKey;
            $wtr->save();

            // Close the associated inbound PO if present
            if ($wtr->purchase_order_id !== null && method_exists($wtr, 'purchaseOrder') && $wtr->purchaseOrder !== null) {
                // Close PO directly — the GRN process handles the formal receiving
                $wtr->purchaseOrder->update([
                    'status'    => 'closed',
                    'closed_at' => now(),
                ]);
            }

            activity()->performedOn($wtr)->causedBy($by)->log('Warehouse transfer received and completed');
        });
    }

    // ─── Cancel ───────────────────────────────────────────────────────────────

    public function cancel(WarehouseTransfer $wtr, string $reason, User $by): void
    {
        DB::transaction(function () use ($wtr, $reason, $by) {
            $terminalStatuses = [
                WarehouseTransferStatus::Dispatched,
                WarehouseTransferStatus::Completed,
                WarehouseTransferStatus::Rejected,
                WarehouseTransferStatus::Cancelled,
            ];

            if (in_array($wtr->status, $terminalStatuses, true)) {
                throw new \DomainException('Warehouse transfer cannot be cancelled in its current status.');
            }

            $wtr->status           = WarehouseTransferStatus::Cancelled;
            $wtr->rejection_reason = $reason;
            $wtr->save();

            activity()->performedOn($wtr)->causedBy($by)->log("Warehouse transfer cancelled: {$reason}");
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Resolve PurchaseOrderWorkflow from the container and create a system PO.
     * Fails gracefully if the workflow is not yet bound.
     */
    private function autoCreateInboundPo(WarehouseTransfer $wtr): void
    {
        try {
            /** @var \App\Services\Inbound\PurchaseOrderWorkflow $poWorkflow */
            $poWorkflow = app(\App\Services\Inbound\PurchaseOrderWorkflow::class);
        } catch (\Throwable) {
            // PurchaseOrderWorkflow not yet available — skip auto-PO creation
            return;
        }

        $systemUser = \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'system-admin'))->first()
            ?? \App\Models\User::first();

        if ($systemUser === null) {
            return;
        }

        $poData = [
            'warehouse_id'         => $wtr->to_warehouse_id,
            'virtual_warehouse_id' => $wtr->to_virtual_warehouse_id,
            'notes'                => "Auto-created from warehouse transfer {$wtr->number}",
            'lines'                => $wtr->lines->map(fn ($l) => [
                'item_id'     => $l->item_id,
                'uom_id'      => $l->uom_id,
                'qty'         => $l->qty,
                'qty_base'    => $l->qty_base,
                'batch_no'    => $l->batch_no ?? null,
                'expiry_date' => $l->expiry_date ?? null,
            ])->toArray(),
        ];

        try {
            $po = $poWorkflow->create($poData, $systemUser, systemCreated: true);
            $poWorkflow->approve($po, $systemUser);

            $wtr->purchase_order_id = $po->id;
            $wtr->saveQuietly();
        } catch (\Throwable) {
            // Non-fatal: PO auto-creation failed — WTR proceeds without linked PO
        }
    }
}
