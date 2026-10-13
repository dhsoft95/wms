<?php

namespace App\Livewire\Admin\Warehouses;

use App\Actions\Foundation\UpdateWarehouseAction;
use App\Exceptions\InUseException;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Edit extends Component
{
    public Warehouse $warehouse;

    public string $code     = '';
    public string $name     = '';
    public string $address  = '';
    public bool   $isActive = true;

    public ?string $defaultDockLocationId      = null;
    public ?string $defaultQuarantineLocationId = null;

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(Warehouse $warehouse): void
    {
        $this->warehouse                   = $warehouse;
        $this->code                        = $warehouse->code;
        $this->name                        = $warehouse->name;
        $this->address                     = $warehouse->address ?? '';
        $this->isActive                    = (bool) $warehouse->is_active;
        $this->defaultDockLocationId       = $warehouse->default_receiving_dock_location_id
            ? (string) $warehouse->default_receiving_dock_location_id
            : null;
        $this->defaultQuarantineLocationId = $warehouse->default_quarantine_location_id
            ? (string) $warehouse->default_quarantine_location_id
            : null;
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function dockLocations(): \Illuminate\Database\Eloquent\Collection
    {
        return Location::where('warehouse_id', $this->warehouse->id)
            ->where('type', 'receiving_dock')
            ->where('status', 'active')
            ->orderBy('code')
            ->get();
    }

    #[Computed]
    public function quarantineLocations(): \Illuminate\Database\Eloquent\Collection
    {
        return Location::where('warehouse_id', $this->warehouse->id)
            ->where('type', 'quarantine')
            ->where('status', 'active')
            ->orderBy('code')
            ->get();
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->code = strtoupper($this->code);

        $this->validate([
            'code'                       => ['required', 'string', 'max:10', Rule::unique('warehouses', 'code')->ignore($this->warehouse->id)],
            'name'                       => ['required', 'string', 'max:255'],
            'address'                    => ['nullable', 'string'],
            'isActive'                   => ['boolean'],
            'defaultDockLocationId'      => ['nullable', 'exists:locations,id'],
            'defaultQuarantineLocationId'=> ['nullable', 'exists:locations,id'],
        ]);

        try {
            UpdateWarehouseAction::execute($this->warehouse, [
                'code'                                => $this->code,
                'name'                                => $this->name,
                'address'                             => $this->address,
                'is_active'                           => $this->isActive,
                'default_receiving_dock_location_id'  => $this->defaultDockLocationId ?: null,
                'default_quarantine_location_id'      => $this->defaultQuarantineLocationId ?: null,
            ]);
        } catch (InUseException $e) {
            $this->addError('isActive', 'Cannot deactivate: this warehouse has existing locations.');
            return;
        }

        $this->dispatch('toast', message: "\"{$this->warehouse->name}\" updated.", type: 'success');

        $this->redirect(route('admin.warehouses.index'), navigate: true);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.warehouses.edit')
            ->layout('components.layouts.app', [
                'title'           => 'Edit Warehouse',
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
