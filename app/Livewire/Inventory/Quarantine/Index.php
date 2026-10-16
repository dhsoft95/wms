<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\Quarantine;

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationType;
use App\Models\Inventory\StockBalance;
use App\Models\Location;
use App\Services\Inventory\StockLedger;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search         = '';
    public bool   $showReleaseModal = false;
    public ?int   $releaseBalanceId  = null;
    public int    $releaseBinId     = 0;
    public string $releaseQty       = '';

    public function openRelease(int $balanceId): void
    {
        $this->authorize('quarantine.manage');
        $this->releaseBalanceId = $balanceId;
        $this->releaseBinId     = 0;
        $this->releaseQty       = '';
        $this->showReleaseModal  = true;
    }

    public function release(StockLedger $ledger): void
    {
        $this->authorize('quarantine.manage');

        $this->validate([
            'releaseBinId' => 'required|integer|exists:locations,id',
            'releaseQty'   => 'required|numeric|min:0.0001',
        ]);

        $balance = StockBalance::findOrFail($this->releaseBalanceId);

        $key = new StockKey(
            itemId:             $balance->item_id,
            batchId:            $balance->batch_id,
            lpnId:              $balance->lpn_id,
            locationId:         $balance->location_id,
            virtualWarehouseId: $balance->virtual_warehouse_id,
            stockStatus:        StockStatus::OnHold,
        );

        try {
            $ledger->move(
                $key,
                $this->releaseBinId,
                $this->releaseQty,
                $balance->item->base_uom_id,
                new StockReference(referenceType: 'quarantine_release', referenceId: $balance->id),
            );

            $this->showReleaseModal = false;
            $this->dispatch('toast', message: 'Stock released from quarantine', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        $quarantineLocationIds = Location::where('type', LocationType::Quarantine)->pluck('id');

        $balances = StockBalance::query()
            ->where(fn ($q) => $q
                ->whereIn('location_id', $quarantineLocationIds)
                ->orWhere('stock_status', StockStatus::OnHold->value)
            )
            ->with(['item', 'location', 'batch', 'virtualWarehouse'])
            ->when($this->search, fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('sku', 'like', "%{$this->search}%")))
            ->paginate(25);

        $binLocations = Location::where('type', '!=', LocationType::Quarantine->value)
            ->where('status', 'active')
            ->get(['id', 'code']);

        return view('livewire.inventory.quarantine.index', compact('balances', 'binLocations'))
            ->layout('components.layouts.app', [
                'title'           => 'Quarantine',
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
