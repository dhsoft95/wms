<?php

namespace App\Livewire\Admin\Locations;

use App\Actions\Foundation\BlockLocationAction;
use App\Actions\Foundation\UnblockLocationAction;
use App\Enums\LocationStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search          = '';
    public string $filterWarehouse = '';
    public string $filterType      = '';
    public string $filterStatus    = '';
    public string $viewMode        = 'list'; // 'list' or 'tree'

    public ?int  $blockingLocationId = null;
    public string $blockReason       = '';

    /** @var array<int> */
    public array $expandedNodes = [];

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function locations(): LengthAwarePaginator
    {
        return Location::with(['warehouse', 'parent'])
            ->when($this->search, function ($q) {
                $q->where(function ($q2) {
                    $q2->where('code', 'like', "%{$this->search}%")
                       ->orWhere('segment_code', 'like', "%{$this->search}%")
                       ->orWhere('barcode', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterWarehouse, fn ($q) => $q->where('warehouse_id', $this->filterWarehouse))
            ->when($this->filterType,      fn ($q) => $q->where('type', $this->filterType))
            ->when($this->filterStatus,    fn ($q) => $q->where('status', $this->filterStatus))
            ->orderBy('code')
            ->paginate(25);
    }

    #[Computed]
    public function warehouses(): \Illuminate\Database\Eloquent\Collection
    {
        return Warehouse::active()->orderBy('code')->get();
    }

    #[Computed]
    public function treeLocations(): \Illuminate\Database\Eloquent\Collection
    {
        return Location::with('children.children.children.children')
            ->whereNull('parent_id')
            ->when($this->filterWarehouse, fn ($q) => $q->where('warehouse_id', $this->filterWarehouse))
            ->orderBy('code')
            ->get();
    }

    // ─── Updaters ─────────────────────────────────────────────────────────────

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterWarehouse(): void
    {
        $this->resetPage();
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    // ─── Block / Unblock ──────────────────────────────────────────────────────

    public function openBlockModal(int $id): void
    {
        $this->blockingLocationId = $id;
        $this->blockReason        = '';
    }

    public function closeBlockModal(): void
    {
        $this->blockingLocationId = null;
        $this->blockReason        = '';
    }

    public function blockLocation(): void
    {
        $this->validate([
            'blockReason' => ['required', 'string', 'max:500'],
        ], [
            'blockReason.required' => 'A reason is required to block a location.',
        ]);

        $location = Location::findOrFail($this->blockingLocationId);

        try {
            BlockLocationAction::execute($location, $this->blockReason, auth()->id());
        } catch (InvalidTransitionException) {
            $this->dispatch('toast', message: "Cannot block \"{$location->code}\" from its current status.", type: 'error');
            $this->closeBlockModal();
            return;
        }

        $this->dispatch('toast', message: "Location \"{$location->code}\" blocked.", type: 'warning');

        $this->closeBlockModal();
        unset($this->locations);
    }

    public function unblockLocation(int $id): void
    {
        $location = Location::findOrFail($id);

        try {
            UnblockLocationAction::execute($location);
        } catch (InvalidTransitionException) {
            $this->dispatch('toast', message: "Cannot unblock \"{$location->code}\" from its current status.", type: 'error');
            return;
        }

        $this->dispatch('toast', message: "Location \"{$location->code}\" unblocked.", type: 'success');

        unset($this->locations);
    }

    // ─── Tree ─────────────────────────────────────────────────────────────────

    public function toggleExpand(int $id): void
    {
        if (in_array($id, $this->expandedNodes, strict: true)) {
            $this->expandedNodes = array_values(array_filter(
                $this->expandedNodes,
                fn ($node) => $node !== $id
            ));
        } else {
            $this->expandedNodes[] = $id;
        }
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.locations.index')
            ->layout('components.layouts.app', [
                'title'           => 'Locations',
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
