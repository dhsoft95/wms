<?php

declare(strict_types=1);

namespace App\Services\Outbound;

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockPosting;
use App\Data\Inventory\StockReference;
use App\Enums\Inbound\ApprovalChainDocumentType;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\StockStatus;
use App\Enums\Outbound\AllocationStatus;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Models\ApprovalChain;
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
        $resubmit = $do->status === DeliveryOrderStatus::AwaitingRequesterCorrection;

        if (! $resubmit) {
            $this->assertCanTransition($do, DeliveryOrderStatus::Submitted);
        }

        DB::transaction(function () use ($do, $by, $resubmit) {
            // On resubmit, clear prior approval records so chain restarts
            if ($resubmit) {
                DeliveryOrderApproval::where('delivery_order_id', $do->id)->delete();
            }

            $do->status       = DeliveryOrderStatus::Submitted;
            $do->submitted_at = now();
            $do->save();

            // Seed approval chain using ApprovalChain configured for MRN + department
            $chains = ApprovalChain::where('document_type', ApprovalChainDocumentType::Mrn->value)
                ->where('department_id', $do->department_id)
                ->orderBy('level')
                ->get();

            if ($chains->isEmpty()) {
                // No chain configured → auto-approve immediately
                $this->doApprove($do, $by);
            } else {
                foreach ($chains as $chain) {
                    DeliveryOrderApproval::create([
                        'delivery_order_id' => $do->id,
                        'level'             => $chain->level,
                        'approver_role'     => 'department',
                        'decision'          => null,
                    ]);
                }
            }

            activity()->performedOn($do)->causedBy($by)->log($resubmit ? 'Resubmitted for approval' : 'Submitted for approval');
        });
    }

    public function returnForCorrection(DeliveryOrder $do, User $by, string $notes = ''): void
    {
        $this->assertCanTransition($do, DeliveryOrderStatus::ReturnedForCorrection);

        DB::transaction(function () use ($do, $by, $notes) {
            // Mark the pending approval record with a 'returned' decision
            DeliveryOrderApproval::where('delivery_order_id', $do->id)
                ->whereNull('decision')
                ->update([
                    'decided_by' => $by->id,
                    'decision'   => 'returned',
                    'notes'      => $notes,
                    'decided_at' => now(),
                ]);

            $do->status = DeliveryOrderStatus::ReturnedForCorrection;
            $do->save();

            activity()->performedOn($do)->causedBy($by)->log("Returned for correction: {$notes}");
        });
    }

    public function forwardToRequester(DeliveryOrder $do, User $by): void
    {
        $this->assertCanTransition($do, DeliveryOrderStatus::AwaitingRequesterCorrection);

        DB::transaction(function () use ($do, $by) {
            $do->status = DeliveryOrderStatus::AwaitingRequesterCorrection;
            $do->save();

            activity()->performedOn($do)->causedBy($by)->log('Forwarded to requester for correction');
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

    public function approve(DeliveryOrder $do, User $by, int $level): void
    {
        DB::transaction(function () use ($do, $by, $level) {
            DeliveryOrderApproval::where('delivery_order_id', $do->id)
                ->where('level', $level)
                ->whereNull('decision')
                ->update([
                    'decided_by' => $by->id,
                    'decision'   => 'approved',
                    'decided_at' => now(),
                ]);

            activity()->performedOn($do)->causedBy($by)->log("Level {$level} approved");

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
            $do->approvals()->whereNull('decision')->update([
                'decided_by' => $by->id,
                'decision'   => 'rejected',
                'notes'      => $notes,
                'decided_at' => now(),
            ]);

            $do->status = DeliveryOrderStatus::Rejected;
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
