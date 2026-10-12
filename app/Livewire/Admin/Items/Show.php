<?php

namespace App\Livewire\Admin\Items;

use App\Models\Inventory\SerialNumber;
use App\Models\Item;
use App\Services\Inventory\StockQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public Item $item;

    public string $activeTab = 'summary';

    public function mount(Item $item): void
    {
        $this->item = $item->load([
            'category',
            'baseUom',
            'defaultVirtualWarehouse',
            'uoms.uom',
            'barcodes.uom',
        ]);
    }

    #[Computed]
    public function stockBalances(): Collection
    {
        return app(StockQueryService::class)->balancesForItem($this->item->id);
    }

    #[Computed]
    public function recentMovements(): Collection
    {
        return app(StockQueryService::class)->recentMovementsForItem($this->item->id, 20);
    }

    #[Computed]
    public function serials()
    {
        if (! $this->item->track_serial) {
            return collect();
        }

        return SerialNumber::with(['location', 'virtualWarehouse', 'batch'])
            ->where('item_id', $this->item->id)
            ->orderBy('serial_no')
            ->paginate(50, ['*'], 'serials_page');
    }

    public function render(): View
    {
        return view('livewire.admin.items.show')
            ->layout('components.layouts.app', [
                'title'           => $this->item->name,
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
