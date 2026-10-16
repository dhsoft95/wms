<?php

namespace App\Livewire\Admin\Locations;

use App\Enums\LocationLevel;
use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Create extends Component
{
    public string $warehouseId  = '';
    public string $parentId     = '';
    public string $level        = '';
    public string $segmentCode  = '';
    public string $type         = 'storage';
    public string $pickSequence = '';
    public string $maxPallets   = '';
    public string $maxWeightKg  = '';
    public string $barcode      = '';

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

    public function updatedType(string $value): void
    {
        // is_pickable is derived at save time; no property to set here
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function availableParentLevels(): array
    {
        return match ($this->level) {
            'zone'        => [],            // root level — no parent
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

    // ─── Save ─────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $isBin = $this->level === 'bin';

        $this->validate([
            'warehouseId' => ['required', 'exists:warehouses,id'],
            'parentId'    => ['nullable', 'exists:locations,id'],
            'level'       => ['required', 'in:zone,aisle,rack,shelf_level,bin'],
            'segmentCode' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'type'        => $isBin ? ['required', 'in:yard_slot,receiving_dock,storage,staging,quarantine'] : [],
            'pickSequence' => ['nullable', 'integer', 'min:0'],
            'maxPallets'   => ['nullable', 'integer', 'min:1'],
            'maxWeightKg'  => ['nullable', 'numeric', 'min:0'],
            'barcode'      => ['nullable', 'string', 'max:100'],
        ]);

        if (! $isBin) {
            $this->type = 'storage';
        }

        $warehouse  = Warehouse::findOrFail($this->warehouseId);
        $parent     = $this->parentId ? Location::findOrFail($this->parentId) : null;
        $isPickable = LocationType::from($this->type)->isPickable();
        $code       = $this->buildCode($parent, $warehouse);
        $path       = $this->buildPath($parent);

        // Check uniqueness against soft-deleted rows too (DB index would catch it, but we want a friendly error)
        if (Location::withTrashed()->where('warehouse_id', $this->warehouseId)->where('code', $code)->exists()) {
            $this->addError('segmentCode', "Location code \"{$code}\" already exists.");
            return;
        }

        Location::create([
            'warehouse_id'  => $this->warehouseId,
            'parent_id'     => $parent?->id,
            'level'         => $this->level,
            'segment_code'  => strtoupper($this->segmentCode),
            'code'          => $code,
            'type'          => $this->type,
            'status'        => 'active',
            'pick_sequence' => $this->pickSequence !== '' ? (int) $this->pickSequence : null,
            'max_pallets'   => $this->maxPallets   ?: null,
            'max_weight_kg' => $this->maxWeightKg  ?: null,
            'barcode'       => $this->barcode ?: $code,
            'path'          => $path,
            'is_pickable'   => $isPickable,
        ]);

        $this->dispatch('toast', message: "Location \"{$code}\" created.", type: 'success');

        $this->redirect(route('admin.locations.index'), navigate: true);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.locations.create')
            ->layout('components.layouts.app', [
                'title'           => 'New Location',
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
