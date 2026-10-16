<?php

declare(strict_types=1);

namespace App\Livewire\Warehouse\Transfers;

use App\Models\StockTransfer;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }

    public function render(): View
    {
        $user      = auth()->user();
        $vwIds     = $user->canAccessAllVirtualWarehouses()
            ? null
            : $user->accessibleVirtualWarehouseIds();

        $transfers = StockTransfer::query()
            ->with(['fromVirtualWarehouse', 'toVirtualWarehouse', 'createdBy'])
            ->when($vwIds, fn ($q) => $q->where(fn ($q2) => $q2
                ->whereIn('from_virtual_warehouse_id', $vwIds)
                ->orWhereIn('to_virtual_warehouse_id', $vwIds)
            ))
            ->when($this->search, fn ($q) => $q->where('number', 'like', "%{$this->search}%"))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(20);

        return view('livewire.warehouse.transfers.index', compact('transfers'))
            ->layout('components.layouts.app', [
                'title'           => 'Department Transfers',
                'breadcrumbGroup' => 'Warehouse Operations',
            ]);
    }
}
