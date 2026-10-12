<?php

namespace App\Livewire\Inventory\Stock;

use App\Models\Inventory\SerialNumber;
use App\Models\Inventory\StockBalance;
use App\Models\ItemCategory;
use App\Models\Location;
use App\Models\VirtualWarehouse;
use App\Services\Inventory\StockQueryService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterCategory = '';
    public string $filterItem = '';
    public string $filterLocation = '';
    public string $filterVw = '';
    public string $filterStatus = '';
    public string $filterExpiring = '';  // days: '', '7', '14', '30'
    public bool $groupByItem = false;

    protected $queryString = [
        'filterItem'     => ['except' => ''],
        'filterLocation' => ['except' => ''],
        'filterVw'       => ['except' => ''],
    ];

    public ?int $viewingSerialsId = null; // StockBalance id

    public function viewSerials(int $balanceId): void
    {
        $this->viewingSerialsId = $balanceId;
    }

    public function closeSerials(): void
    {
        $this->viewingSerialsId = null;
    }

    #[Computed]
    public function viewingSerialsBalance(): ?array
    {
        if (! $this->viewingSerialsId) {
            return null;
        }

        $balance = StockBalance::with('item')->find($this->viewingSerialsId);
        if (! $balance) {
            return null;
        }

        $serials = SerialNumber::where('item_id', $balance->item_id)
            ->where('location_id', $balance->location_id)
            ->where('virtual_warehouse_id', $balance->virtual_warehouse_id)
            ->where('stock_status', $balance->stock_status)
            ->where(fn ($q) => $balance->batch_id === null
                ? $q->whereNull('batch_id')
                : $q->where('batch_id', $balance->batch_id))
            ->where(fn ($q) => $balance->lpn_id === null
                ? $q->whereNull('lpn_id')
                : $q->where('lpn_id', $balance->lpn_id))
            ->orderBy('serial_no')
            ->get();

        return ['balance' => $balance, 'serials' => $serials];
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingFilterCategory(): void { $this->resetPage(); }
    public function updatingFilterItem(): void { $this->resetPage(); }
    public function updatingFilterLocation(): void { $this->resetPage(); }
    public function updatingFilterVw(): void { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }
    public function updatingFilterExpiring(): void { $this->resetPage(); }

    #[Computed]
    public function balances()
    {
        return app(StockQueryService::class)->stockOnHand(
            itemId: $this->filterItem ? (int) $this->filterItem : null,
            locationId: $this->filterLocation ? (int) $this->filterLocation : null,
            virtualWarehouseId: $this->filterVw ? (int) $this->filterVw : null,
            stockStatus: $this->filterStatus ?: null,
            expiringWithinDays: $this->filterExpiring ? (int) $this->filterExpiring : null,
            groupByItem: $this->groupByItem,
            user: auth()->user(),
            perPage: 25,
            search: $this->search ?: null,
            categoryId: $this->filterCategory ? (int) $this->filterCategory : null,
        );
    }

    #[Computed]
    public function categories()
    {
        return ItemCategory::active()->orderBy('name')->get();
    }

    #[Computed]
    public function locations()
    {
        return Location::active()->orderBy('code')->limit(200)->get();
    }

    #[Computed]
    public function virtualWarehouses()
    {
        $user = auth()->user();

        return VirtualWarehouse::active()
            ->when(! $user->canAccessAllVirtualWarehouses(), fn ($q) =>
                $q->whereHas('users', fn ($uq) => $uq->where('users.id', $user->id))
            )
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function stats(): array
    {
        return app(StockQueryService::class)->stockStats(auth()->user());
    }

    public function render()
    {
        return view('livewire.inventory.stock.index')
            ->layout('components.layouts.app', [
                'title'           => 'Stock on Hand',
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
