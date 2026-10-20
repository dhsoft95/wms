<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\Quarantine;

use App\Data\Inventory\StockKey;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationType;
use App\Models\Inventory\SerialNumber;
use App\Models\Inventory\StockBalance;
use App\Models\Location;
use App\Services\Inventory\StockLedger;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search            = '';
    public bool   $showReleaseModal  = false;
    public ?int   $releaseBalanceId  = null;
    public int    $releaseBinId      = 0;
    public string $releaseQty        = '';
    public array  $selectedSerials   = [];
    public array  $availableSerials  = [];
    public bool   $isSerialTracked   = false;

    public function openRelease(int $balanceId): void
    {
        $this->authorize('quarantine.manage');

        $balance = StockBalance::with('item')->findOrFail($balanceId);

        $this->releaseBalanceId = $balanceId;
        $this->releaseBinId     = 0;
        $this->releaseQty       = '';
        $this->selectedSerials  = [];
        $this->isSerialTracked  = (bool) $balance->item?->track_serial;

        if ($this->isSerialTracked) {
            $this->availableSerials = SerialNumber::where('item_id', $balance->item_id)
                ->where('location_id', $balance->location_id)
                ->where('virtual_warehouse_id', $balance->virtual_warehouse_id)
                ->where('stock_status', $balance->stock_status)
                ->when($balance->batch_id, fn ($q) => $q->where('batch_id', $balance->batch_id))
                ->orderBy('serial_no')
                ->pluck('serial_no')
                ->toArray();
        } else {
            $this->availableSerials = [];
        }

        $this->showReleaseModal = true;
    }

    public function updatedSelectedSerials(): void
    {
        if ($this->isSerialTracked) {
            $this->releaseQty = (string) count($this->selectedSerials);
        }
    }

    public function release(StockLedger $ledger): void
    {
        $this->authorize('quarantine.manage');

        $rules = [
            'releaseBinId' => 'required|integer|exists:locations,id',
        ];

        if ($this->isSerialTracked) {
            $rules['selectedSerials']   = 'required|array|min:1';
            $rules['selectedSerials.*'] = 'string';
        } else {
            $rules['releaseQty'] = 'required|numeric|min:0.0001';
        }

        $this->validate($rules);

        $balance = StockBalance::findOrFail($this->releaseBalanceId);

        $key = new StockKey(
            itemId:             $balance->item_id,
            batchId:            $balance->batch_id,
            lpnId:              $balance->lpn_id,
            locationId:         $balance->location_id,
            virtualWarehouseId: $balance->virtual_warehouse_id,
            stockStatus:        StockStatus::OnHold,
        );

        $qty     = $this->isSerialTracked ? (string) count($this->selectedSerials) : $this->releaseQty;
        $serials = $this->isSerialTracked ? $this->selectedSerials : [];

        try {
            $ledger->move(
                $key,
                $this->releaseBinId,
                $qty,
                $balance->item->base_uom_id,
                new StockReference(referenceType: 'quarantine_release', referenceId: $balance->id),
                $serials,
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
