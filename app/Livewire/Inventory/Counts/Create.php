<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\Counts;

use App\Enums\LocationLevel;
use App\Models\Location;
use App\Models\VirtualWarehouse;
use App\Services\Inventory\CycleCountWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Create extends Component
{
    public int    $virtualWarehouseId = 0;
    public string $notes              = '';
    public array  $locationIds        = [];
    public array  $itemIds            = [];

    public function save(CycleCountWorkflow $workflow): void
    {
        $this->authorize('create', \App\Models\CycleCount::class);

        $this->validate([
            'virtualWarehouseId' => 'required|integer|exists:virtual_warehouses,id',
            'locationIds'        => 'array',
            'locationIds.*'      => 'integer|exists:locations,id',
            'itemIds'            => 'array',
            'itemIds.*'          => 'integer|exists:items,id',
        ]);

        if (empty($this->locationIds) && empty($this->itemIds)) {
            $this->addError('locationIds', 'Select at least one bin or item to count.');
            return;
        }

        try {
            $count = $workflow->create([
                'virtual_warehouse_id' => $this->virtualWarehouseId,
                'notes'                => $this->notes ?: null,
                'location_ids'         => $this->locationIds,
                'item_ids'             => $this->itemIds,
            ], auth()->user());

            $this->dispatch('toast', message: "Cycle count {$count->number} created", type: 'success');
            $this->redirect(route('inventory.counts.show', $count), navigate: true);
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        $warehouses = VirtualWarehouse::all();
        $bins       = Location::where('level', LocationLevel::Bin)->where('status', 'active')->get(['id', 'code']);

        return view('livewire.inventory.counts.create', compact('warehouses', 'bins'))
            ->layout('components.layouts.app', [
                'title'           => 'New Cycle Count',
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
