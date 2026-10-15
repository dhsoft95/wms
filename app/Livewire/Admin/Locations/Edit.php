<?php

namespace App\Livewire\Admin\Locations;

use App\Actions\Foundation\UpdateLocationAction;
use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Edit extends Component
{
    public Location $location;

    public string $warehouseId  = '';
    public string $parentId     = '';
    public string $level        = '';
    public string $segmentCode  = '';
    public string $type         = 'storage';
    public string $pickSequence = '';
    public string $maxPallets   = '';
    public string $maxWeightKg  = '';
    public string $barcode      = '';

    // ─── Mount ────────────────────────────────────────────────────────────────

    public function mount(Location $location): void
    {
        $this->location = $location->load(['warehouse', 'parent']);

        $this->warehouseId  = (string) $location->warehouse_id;
        $this->parentId     = (string) ($location->parent_id ?? '');
        $this->level        = $location->level->value;
        $this->segmentCode  = $location->segment_code;
        $this->type         = $location->type->value;
        $this->pickSequence = (string) ($location->pick_sequence ?? '');
        $this->maxPallets   = (string) ($location->max_pallets ?? '');
        $this->maxWeightKg  = (string) ($location->max_weight_kg ?? '');
        $this->barcode      = $location->barcode ?? '';
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function warehouses(): \Illuminate\Database\Eloquent\Collection
    {
        return Warehouse::active()->orderBy('code')->get();
    }

    #[Computed]
    public function parentOptions(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->warehouseId || ! $this->level) {
            return collect();
        }

        $parentLevels = $this->availableParentLevels();

        if (empty($parentLevels)) {
            return collect();
        }

        return Location::where('warehouse_id', $this->warehouseId)
            ->whereIn('level', $parentLevels)
            ->where('id', '!=', $this->location->id)
            ->orderBy('code')
            ->get();
    }

    // ─── Updaters ─────────────────────────────────────────────────────────────

    public function updatedWarehouseId(): void
    {
        $this->parentId = '';
    }

    public function updatedLevel(): void
    {
        $this->parentId = '';
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function availableParentLevels(): array
    {
        return match ($this->level) {
            'zone'        => [],
            'aisle'       => ['zone'],
            'rack'        => ['aisle'],
            'shelf_level' => ['rack'],
            'bin'         => ['shelf_level'],
            default       => [],
        };
    }

    private function buildCode(?Location $parent, Warehouse $warehouse): string
    {
        if ($parent) {
            return $parent->code . '-' . strtoupper($this->segmentCode);
        }

        return $warehouse->code . '-' . strtoupper($this->segmentCode);
    }

    private function buildPath(?Location $parent): string
    {
        if ($parent) {
            return $parent->path . $parent->id . '/';
        }

        return '/';
    }

    /**
     * Rebuild the code for a descendant by replacing the old ancestor segment
     * with the new code prefix derived from its own path.
     */
    private function rebuildDescendantCode(Location $descendant, string $oldCode, string $newCode): string
    {
        // The descendant code starts with the old location code
        return $newCode . substr($descendant->code, strlen($oldCode));
    }

    // ─── Save ─────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->validate([
            'warehouseId' => ['required', 'exists:warehouses,id'],
            'parentId'    => ['nullable', 'exists:locations,id'],
            'level'       => ['required', 'in:zone,aisle,rack,shelf_level,bin'],
            'segmentCode' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'type'         => ['required', 'in:yard_slot,receiving_dock,storage,staging,quarantine'],
            'pickSequence' => ['nullable', 'integer', 'min:0'],
            'maxPallets'   => ['nullable', 'integer', 'min:1'],
            'maxWeightKg'  => ['nullable', 'numeric', 'min:0'],
            'barcode'      => ['nullable', 'string', 'max:100'],
        ]);

        UpdateLocationAction::execute($this->location, [
            'warehouse_id'  => $this->warehouseId,
            'parent_id'     => $this->parentId ?: null,
            'level'         => $this->level,
            'segment_code'  => $this->segmentCode,
            'type'          => $this->type,
            'pick_sequence' => $this->pickSequence,
            'max_pallets'   => $this->maxPallets,
            'max_weight_kg' => $this->maxWeightKg,
            'barcode'       => $this->barcode,
        ]);

        $this->dispatch('toast', message: "Location \"{$this->location->code}\" updated.", type: 'success');

        $this->redirect(route('admin.locations.index'), navigate: true);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.locations.edit')
            ->layout('components.layouts.app', [
                'title'           => 'Edit Location',
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
