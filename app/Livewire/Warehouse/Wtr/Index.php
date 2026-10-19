<?php

declare(strict_types=1);

namespace App\Livewire\Warehouse\Wtr;

use App\Enums\Transfers\WarehouseTransferStatus;
use App\Models\WarehouseTransfer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function transfers()
    {
        return WarehouseTransfer::with(['fromWarehouse', 'toWarehouse', 'createdBy'])
            ->when($this->search, fn ($q) => $q->where('number', 'like', "%{$this->search}%"))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function render(): View
    {
        return view('livewire.warehouse.wtr.index', [
            'statuses' => WarehouseTransferStatus::cases(),
        ])->layout('components.layouts.app', [
            'title'           => 'Warehouse Transfers',
            'breadcrumbGroup' => 'Operations',
        ]);
    }
}
