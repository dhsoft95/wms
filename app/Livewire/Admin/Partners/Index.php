<?php

namespace App\Livewire\Admin\Partners;

use App\Models\Partner;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterType = '';
    public string $filterStatus = '';
    public ?int $confirmingDeactivate = null;

    public function updatingSearch(): void
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

    #[Computed]
    public function partners(): LengthAwarePaginator
    {
        return Partner::with([])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('code', 'like', '%' . $this->search . '%')
                      ->orWhere('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterType, function ($query) {
                $query->whereJsonContains('type', $this->filterType);
            })
            ->when($this->filterStatus, function ($query) {
                $query->where('is_active', $this->filterStatus === 'active');
            })
            ->latest()
            ->paginate(20);
    }

    public function deactivate(int $id): void
    {
        $partner = Partner::findOrFail($id);
        $partner->update(['is_active' => false]);
        $this->confirmingDeactivate = null;
        $this->dispatch('toast', message: $partner->name . ' has been deactivated.', type: 'success');
    }

    public function activate(int $id): void
    {
        $partner = Partner::findOrFail($id);
        $partner->update(['is_active' => true]);
        $this->dispatch('toast', message: $partner->name . ' has been activated.', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.partners.index')
            ->layout('components.layouts.app', [
                'title'           => 'Partners',
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
