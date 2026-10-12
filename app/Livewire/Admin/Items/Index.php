<?php

namespace App\Livewire\Admin\Items;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\VirtualWarehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterCategory = '';
    public string $filterStatus = '';
    public string $filterVw = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterCategory(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterVw(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return Item::with(['category', 'baseUom', 'defaultVirtualWarehouse'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('sku', 'like', '%' . $this->search . '%')
                      ->orWhere('name', 'like', '%' . $this->search . '%')
                      ->orWhereHas('barcodes', fn ($bq) => $bq->where('barcode', 'like', '%' . $this->search . '%'));
                });
            })
            ->when($this->filterCategory, fn ($q) => $q->where('category_id', $this->filterCategory))
            ->when($this->filterStatus !== '', function ($query) {
                $query->where('is_active', (bool) $this->filterStatus);
            })
            ->when($this->filterVw, fn ($q) => $q->where('default_virtual_warehouse_id', $this->filterVw))
            ->latest()
            ->paginate(20);
    }

    #[Computed]
    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return ItemCategory::active()->orderBy('name')->get();
    }

    #[Computed]
    public function virtualWarehouses(): \Illuminate\Database\Eloquent\Collection
    {
        return VirtualWarehouse::active()->orderBy('name')->get();
    }

    public function deactivate(int $id): void
    {
        $item = Item::findOrFail($id);
        $item->update(['is_active' => false]);
        $this->dispatch('toast', message: $item->name . ' deactivated.', type: 'info');
    }

    public function activate(int $id): void
    {
        $item = Item::findOrFail($id);
        $item->update(['is_active' => true]);
        $this->dispatch('toast', message: $item->name . ' activated.', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.items.index')
            ->layout('components.layouts.app', [
                'title'           => 'Items',
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
