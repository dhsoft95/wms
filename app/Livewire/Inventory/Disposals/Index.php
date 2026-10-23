<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\Disposals;

use App\Enums\Inventory\DisposalStatus;
use App\Models\DisposalRequest;
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
        $disposals = DisposalRequest::query()
            ->with(['createdBy', 'virtualWarehouse'])
            ->withCount('lines')
            ->when($this->search, fn ($q) => $q->where('number', 'like', "%{$this->search}%"))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(20);

        return view('livewire.inventory.disposals.index', compact('disposals'))
            ->layout('components.layouts.app', [
                'title'           => 'Disposal Requests',
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
