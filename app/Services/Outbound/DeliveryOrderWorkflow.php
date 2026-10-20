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
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderApproval;
use App\Models\ProofOfDelivery;
use App\Models\PodFile;
use App\Models\User;
use App\Services\Foundation\SettingsService;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\StockReservations;
use Illuminate\Support\Facades\DB;

final class DeliveryOrderWorkflow
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly StockReservations $reservations,
        private readonly StockLedger $ledger,
    ) {}

    public function submit(DeliveryOrder $do, User $by): void
    {
        $this->assertCanTransition($do, DeliveryOrderStatus::Submitted);

        DB::transaction(function () use ($do, $by) {
            $do->load('lines.item');

            foreach ($do->lines as $line) {
                $available = \App\Models\Inventory\StockBalance::where('item_id', $line->item_id)
                    ->where('virtual_warehouse_id', $do->virtual_warehouse_id)
                    ->where('stock_status', \App\Enums\Inventory\StockStatus::Available->value)
                    ->get()
                    ->sum(fn ($b) => (float) $b->qtyAvailable());

                $sku = $line->item?->sku ?? "line {$line->line_no}";

                if (bccomp((string) $available, '0', 4) <= 0) {
                    throw new \DomainException("No stock available for {$sku}.");
                }
                if (bccomp((string) $available, (string) $line->requested_qty_base, 4) < 0) {
                    throw new \DomainException("Only {$available} available for {$sku} (requested {$line->requested_qty_base}).");
                }
            }

            $do->status       = DeliveryOrderStatus::Submitted;
            $do->submitted_at = now();
            $do->save();

            activity()->performedOn($do)->causedBy($by)->log('Submitted for approval');
        });
    }

    public function approveOrder(DeliveryOrder $do, User $by): void
    {
        $this->assertCanTransition($do, DeliveryOrderStatus::Approved);

        DB::transaction(function () use ($do, $by) {
            $do->status = DeliveryOrderStatus::Approved;
            $do->save();

            activity()->performedOn($do)->causedBy($by)->log('Approved');
        });
    }

    public function approve(DeliveryOrder $do, User $by, string $approverRole, string $notes = ''): void
    {
        DB::transaction(function () use ($do, $by, $approverRole, $notes) {
            $approval = $do->approvals()
                ->where('approver_role', $approverRole)
                ->whereNull('decision')
                ->first();

            if ($approval !== null) {
                $approval->update([
                    'decided_by' => $by->id,
                    'decision'   => 'approved',
                    'notes'      => $notes,
                    'decided_at' => now(),
                ]);
            }

            activity()->performedOn($do)->causedBy($by)->log("{$approverRole} approved");

            // Check if all levels approved (or no records at all)
            $pendingCount = $do->approvals()->whereNull('decision')->count();
            if ($pendingCount === 0) {
                $this->doApprove($do, $by);
            }
        });
    }

    public function reject(DeliveryOrder $do, User $by, string $notes): void
    {
        if ($do->status !== DeliveryOrderStatus::Submitted) {
            throw new \DomainException('Can only reject a submitted delivery order.');
        }

        DB::transaction(function () use ($do, $by, $notes) {
            // Mark pending approval as rejected
            $do->approvals()->whereNull('decision')->update([
                'decided_by' => $by->id,
                'decision'   => 'rejected',
                'notes'      => $notes,
                'decided_at' => now(),
            ]);

            $do->status = DeliveryOrderStatus::Draft;
            $do->save();

            activity()->performedOn($do)->causedBy($by)->log("Rejected: {$notes}");
        });
    }

    public function cancel(DeliveryOrder $do, User $by, string $reason = ''): void
    {
        if (in_array($do->status, [DeliveryOrderStatus::Dispatched, DeliveryOrderStatus::Delivered], true)) {
            throw new \DomainException('Cannot cancel a dispatched or delivered order.');
        }

        DB::transaction(function () use ($do, $by, $reason) {
            // Release all reserved allocations
            foreach ($do->lines()->with('allocations.stockReservation')->get() as $line) {
                foreach ($line->allocations as $allocation) {
                    if ($allocation->status === AllocationStatus::Reserved && $allocation->stockReservation) {
                        $this->reservations->release($allocation->stockReservation);
                    }
                    // If picked → move stock back from staging to source bin
                    if ($allocation->status === AllocationStatus::Picked) {
                        $this->moveBackFromStaging($allocation, $do, $by);
                    }
                    $allocation->status = AllocationStatus::Released;
                    $allocation->save();
                }
            }

            $do->status = DeliveryOrderStatus::Cancelled;
            $do->save();

            activity()->performedOn($do)->causedBy($by)->log("Cancelled: {$reason}");
        });
    }

    public function accept(DeliveryOrder $do, User $by): void
    {
        $this->assertCanTransition($do, DeliveryOrderStatus::Acceptance);

        DB::transaction(function () use ($do, $by) {
            $do->status        = DeliveryOrderStatus::Acceptance;
            $do->acceptance_at = now();
            $do->save();

            activity()->performedOn($do)->causedBy($by)->log('Accepted for processing');
        });
    }

    public function assignTeam(DeliveryOrder $do, User $by): void
    {
        $this->assertCanTransition($do, DeliveryOrderStatus::Assigned);

        DB::transaction(function () use ($do, $by) {
            $do->status      = DeliveryOrderStatus::Assigned;
            $do->assigned_at = now();
            $do->save();

            activity()->performedOn($do)->causedBy($by)->log('Assigned to warehouse team');
        });
    }

    public function recordPod(DeliveryOrder $do, array $data, User $by): ProofOfDelivery
    {
        return DB::transaction(function () use ($do, $data, $by) {
            $pod = ProofOfDelivery::create([
                'delivery_order_id' => $do->id,
                'received_by_name'  => $data['received_by_name'],
                'received_at'       => $data['received_at'],
                'notes'             => $data['notes'] ?? null,
                'signature_path'    => $data['signature_path'] ?? null,
                'uploaded_by'       => $by->id,
            ]);

            foreach ($data['file_paths'] ?? [] as $path) {
                PodFile::create([
                    'proof_of_delivery_id' => $pod->id,
                    'path'                 => $path,
                    'disk'                 => $data['disk'] ?? 'local',
                ]);
            }

            $do->status = DeliveryOrderStatus::Delivered;
            $do->save();

            activity()->performedOn($do)->causedBy($by)->log('Proof of delivery recorded');

            return $pod;
        });
    }

    private function doApprove(DeliveryOrder $do, User $by): void
    {
        $do->status = DeliveryOrderStatus::Approved;
        $do->save();
        activity()->performedOn($do)->causedBy($by)->log('Approved');
    }

    private function assertCanTransition(DeliveryOrder $do, DeliveryOrderStatus $to): void
    {
        if (! $do->status->canTransitionTo($to)) {
            throw new \DomainException(
                "Cannot transition delivery order from {$do->status->label()} to {$to->label()}."
            );
        }
    }

    private function moveBackFromStaging(
        \App\Models\DeliveryOrderAllocation $allocation,
        DeliveryOrder $do,
        User $by
    ): void {
        $staging = $do->warehouse->defaultStagingLocation;
        if ($staging === null) {
            return; // No staging location configured, skip
        }

        $posting = new StockPosting(
            type: MovementType::Relocation,
            reference: new StockReference(
                referenceType: 'delivery_order',
                referenceId: $do->id,
                referenceNo: $do->number,
            ),
            lines: [
                new StockLine(
                    itemId: $allocation->item_id,
                    locationId: $staging->id,
                    virtualWarehouseId: $do->virtual_warehouse_id,
                    stockStatus: StockStatus::Available,
                    enteredQty: bcsub('0', (string) $allocation->qty, 4),
                    enteredUomId: $allocation->line->uom_id,
                    batchId: $allocation->batch_id,
                    lpnId: $allocation->lpn_id,
                ),
                new StockLine(
                    itemId: $allocation->item_id,
                    locationId: $allocation->location_id,
                    virtualWarehouseId: $do->virtual_warehouse_id,
                    stockStatus: StockStatus::Available,
                    enteredQty: (string) $allocation->qty,
                    enteredUomId: $allocation->line->uom_id,
                    batchId: $allocation->batch_id,
                    lpnId: $allocation->lpn_id,
                ),
            ],
        );

        $this->ledger->post($posting, $by);
    }
}
