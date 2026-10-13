<?php

declare(strict_types=1);

namespace App\Services\Inbound;

use App\Contracts\ContainerReceivingGate;
use App\Enums\ContainerDiscrepancyType;
use App\Enums\ContainerStatus;
use App\Events\Inbound\ContainerArrived;
use App\Events\Inbound\ContainerSealMismatch;
use App\Events\Inbound\ContainerUnloadingStarted;
use App\Exceptions\InvalidTransitionException;
use App\Models\Container;
use App\Models\ContainerDiscrepancy;
use App\Models\ContainerStatusHistory;
use App\Models\ContainerYardMove;
use App\Models\Location;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class ContainerWorkflow
{
    public function __construct(private readonly ContainerReceivingGate $gate) {}

    public function confirmArrival(
        Container $container,
        DateTimeInterface $arrivedAt,
        string $arrivalSealNo,
        User $user
    ): void {
        DB::transaction(function () use ($container, $arrivedAt, $arrivalSealNo, $user) {
            $container->arrived_at      = $arrivedAt;
            $container->arrival_seal_no = $arrivalSealNo;
            $container->seal_verified   = ($container->seal_no !== null && $container->seal_no === $arrivalSealNo);
            $container->save();

            $this->transition($container, ContainerStatus::Arrived, $user);

            // Seal mismatch: create discrepancy + fire event
            if ($container->seal_no !== null && $container->seal_no !== $arrivalSealNo) {
                ContainerDiscrepancy::create([
                    'container_id' => $container->id,
                    'type'         => ContainerDiscrepancyType::SealMismatch,
                    'description'  => "Expected seal: {$container->seal_no}. Arrival seal: {$arrivalSealNo}.",
                    'reported_by'  => $user->id,
                ]);
                event(new ContainerSealMismatch($container, $container->seal_no, $arrivalSealNo));
            }

            event(new ContainerArrived($container));
        });
    }

    public function park(Container $container, Location $location, User $user): void
    {
        DB::transaction(function () use ($container, $location, $user) {
            $this->assertYardSlotFree($location, $container);

            $container->yard_location_id = $location->id;
            $container->save();

            $this->transition($container, ContainerStatus::Parked, $user);
        });
    }

    public function moveSlot(Container $container, Location $newLocation, string $reason, User $user): void
    {
        DB::transaction(function () use ($container, $newLocation, $reason, $user) {
            $this->assertYardSlotFree($newLocation, $container);

            $fromLocationId = $container->yard_location_id;

            $container->yard_location_id = $newLocation->id;
            $container->save();

            ContainerYardMove::create([
                'container_id'     => $container->id,
                'from_location_id' => $fromLocationId,
                'to_location_id'   => $newLocation->id,
                'user_id'          => $user->id,
                'reason'           => $reason,
            ]);

            activity()
                ->performedOn($container)
                ->causedBy($user)
                ->withProperties([
                    'from_location_id' => $fromLocationId,
                    'to_location_id'   => $newLocation->id,
                    'reason'           => $reason,
                ])
                ->log('Yard slot moved');
        });
    }

    public function startUnloading(Container $container, User $user): void
    {
        DB::transaction(function () use ($container, $user) {
            $lines = $container->expectedLines()->get();

            if ($lines->isEmpty()) {
                throw new \RuntimeException('Cannot start unloading: no expected lines have been added to this container.');
            }

            $unmapped = $lines->filter(fn($l) => $l->item_id === null || $l->virtual_warehouse_id === null);
            if ($unmapped->isNotEmpty()) {
                throw new \RuntimeException('Cannot start unloading: all expected lines must be mapped to an item and virtual warehouse first.');
            }

            $container->unloading_started_at = now();
            $container->save();

            $this->transition($container, ContainerStatus::Unloading, $user);

            event(new ContainerUnloadingStarted($container));
        });
    }

    public function markUnloaded(Container $container, User $user): void
    {
        DB::transaction(function () use ($container, $user) {
            $blocked = $this->gate->checkReadyForUnloaded($container->id);
            if ($blocked !== null) {
                throw new \RuntimeException($blocked);
            }

            $container->unloaded_at = now();
            $container->save();

            $this->transition($container, ContainerStatus::Unloaded, $user);
        });
    }

    public function release(
        Container $container,
        DateTimeInterface $releasedAt,
        int $releasedToPartnerId,
        ?string $releaseNotes,
        User $user
    ): void {
        DB::transaction(function () use ($container, $releasedAt, $releasedToPartnerId, $releaseNotes, $user) {
            $container->released_at            = $releasedAt;
            $container->released_to_partner_id = $releasedToPartnerId;
            $container->release_notes          = $releaseNotes;
            $container->yard_location_id       = null; // free yard slot
            $container->save();

            $this->transition($container, ContainerStatus::Released, $user);
        });
    }

    public function cancel(Container $container, string $reason, User $user): void
    {
        DB::transaction(function () use ($container, $reason, $user) {
            $container->yard_location_id = null; // free slot if any
            $container->save();

            $this->transition($container, ContainerStatus::Cancelled, $user, $reason);
        });
    }

    private function transition(Container $container, ContainerStatus $to, User $user, ?string $notes = null): void
    {
        $from = $container->status;

        if (!$from->canTransitionTo($to)) {
            throw new InvalidTransitionException($from->value, $to->value);
        }

        $container->status = $to;
        $container->save();

        ContainerStatusHistory::create([
            'container_id' => $container->id,
            'from_status'  => $from->value,
            'to_status'    => $to->value,
            'user_id'      => $user->id,
            'notes'        => $notes,
        ]);

        activity()
            ->performedOn($container)
            ->causedBy($user)
            ->withProperties(['from' => $from->value, 'to' => $to->value])
            ->log("Status changed to {$to->label()}");
    }

    private function assertYardSlotFree(Location $location, Container $currentContainer): void
    {
        if ($location->type->value !== 'yard_slot') {
            throw new \InvalidArgumentException("Location {$location->code} is not a yard slot.");
        }

        $occupied = Container::where('yard_location_id', $location->id)
            ->where('id', '!=', $currentContainer->id)
            ->whereIn('status', array_column(
                array_filter(ContainerStatus::cases(), fn(ContainerStatus $s) => $s->isActive()),
                'value'
            ))
            ->exists();

        if ($occupied) {
            throw new \RuntimeException("Yard slot {$location->code} is already occupied by another active container.");
        }
    }
}
