<?php

namespace App\Livewire\Inventory\Serials;

use App\Enums\Inventory\SerialStatus;
use App\Models\Inventory\SerialNumber;
use App\Models\VirtualWarehouse;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Lookup extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $filterStatus = '';
    public string $filterVw     = '';
    public ?int $selectedId     = null;

    protected $queryString = [
        'search'       => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterVw'     => ['except' => ''],
    ];

    public function updatingSearch(): void  { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }
    public function updatingFilterVw(): void { $this->resetPage(); }

    public function loadHistory(int $serialId): void
    {
        $this->selectedId = $serialId;
    }

    #[Computed]
    public function serials()
    {
        $user = auth()->user();

        return SerialNumber::with(['item', 'location', 'virtualWarehouse', 'batch', 'lastTransaction'])
            ->when($this->search !== '', function ($q) {
                $q->where('serial_no', 'like', '%' . strtoupper(trim($this->search)) . '%')
                  ->orWhereHas('item', fn ($iq) => $iq->where('sku', 'like', '%' . $this->search . '%')
                      ->orWhere('name', 'like', '%' . $this->search . '%'));
            })
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterVw !== '', fn ($q) => $q->where('virtual_warehouse_id', (int) $this->filterVw))
            ->orderByDesc('updated_at')
            ->paginate(25);
    }

    #[Computed]
    public function virtualWarehouses()
    {
        return VirtualWarehouse::active()->orderBy('name')->get();
    }

    #[Computed]
    public function statuses(): array
    {
        return SerialStatus::cases();
    }

    #[Computed]
    public function selectedSerial(): ?SerialNumber
    {
        if ($this->selectedId === null) {
            return null;
        }

        return SerialNumber::with(['item', 'location', 'virtualWarehouse', 'batch'])->find($this->selectedId);
    }

    #[Computed]
    public function serialHistory(): array
    {
        if ($this->selectedId === null) {
            return [];
        }

        return SerialNumber::find($this->selectedId)
            ?->movements()
            ->with(['transaction', 'location'])
            ->orderByDesc('created_at')
            ->get()
            ->all() ?? [];
    }

    public function render()
    {
        return view('livewire.inventory.serials.lookup')
            ->layout('components.layouts.app', [
                'title'           => 'Serial Lookup',
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
