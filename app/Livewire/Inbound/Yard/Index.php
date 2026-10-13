<?php

declare(strict_types=1);

namespace App\Livewire\Inbound\Yard;

use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\Location;
use App\Services\Inbound\ContainerQueryService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public string $filterZone   = '';
    public bool   $showFreeOnly = false;
    public ?int   $selectedSlotId = null;

    #[Computed]
    public function yardSlots()
    {
        $query = Location::where('type', 'yard_slot')
            ->where('status', 'active')
            ->orderBy('code');

        if ($this->filterZone !== '') {
            $query->where('code', 'like', $this->filterZone . '%');
        }

        $slots = $query->get();

        if ($this->showFreeOnly) {
            $occupiedIds = array_keys($this->activeContainersBySlot);
            $slots = $slots->whereNotIn('id', $occupiedIds)->values();
        }

        return $slots;
    }

    #[Computed]
    public function activeContainersBySlot(): array
    {
        $user         = auth()->user();
        $queryService = app(ContainerQueryService::class);
        $activeValues = array_column(
            array_filter(ContainerStatus::cases(), fn($s) => $s->isActive()),
            'value'
        );

        return $queryService->queryFor($user)
            ->whereNotNull('yard_location_id')
            ->whereIn('status', $activeValues)
            ->with(['yardLocation', 'shippingLinePartner', 'expectedLines'])
            ->get()
            ->keyBy('yard_location_id')
            ->toArray();
    }

    #[Computed]
    public function selectedContainer(): ?Container
    {
        if (!$this->selectedSlotId) {
            return null;
        }
        $activeValues = array_column(
            array_filter(ContainerStatus::cases(), fn($s) => $s->isActive()),
            'value'
        );
        return Container::where('yard_location_id', $this->selectedSlotId)
            ->whereIn('status', $activeValues)
            ->with(['shippingLinePartner', 'expectedLines', 'yardLocation'])
            ->first();
    }

    #[Computed]
    public function zones(): array
    {
        return Location::where('type', 'yard_slot')
            ->where('status', 'active')
            ->pluck('code')
            ->map(fn($code) => implode('-', array_slice(explode('-', $code), 0, 2)))
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    #[Computed]
    public function totalSlots(): int
    {
        return Location::where('type', 'yard_slot')->where('status', 'active')->count();
    }

    #[Computed]
    public function occupiedCount(): int
    {
        return count($this->activeContainersBySlot);
    }

    public function selectSlot(int $locationId): void
    {
        $this->selectedSlotId = ($this->selectedSlotId === $locationId) ? null : $locationId;
    }

    public function render()
    {
        return view('livewire.inbound.yard.index')
            ->layout('components.layouts.app', [
                'title'           => 'Yard Map',
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
