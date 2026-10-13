<?php

namespace App\Livewire\Inbound\Grn;

use App\Enums\GrnSource;
use App\Enums\GrnStatus;
use App\Services\Inbound\GrnQueryService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $statusFilter = 'all';
    public string $sourceFilter = '';
    public string $dateFrom     = '';
    public string $dateTo       = '';

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function updatedSearch(): void    { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedSourceFilter(): void { $this->resetPage(); }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function grns(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = app(GrnQueryService::class)
            ->queryFor(auth()->user())
            ->with(['warehouse', 'supplierPartner', 'container'])
            ->withCount('lines');

        if ($this->search !== '') {
            $search = '%' . $this->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', $search)
                  ->orWhereHas('supplierPartner', fn ($sq) => $sq->where('name', 'like', $search))
                  ->orWhereHas('container', fn ($cq) => $cq->where('number', 'like', $search));
            });
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->sourceFilter !== '') {
            $query->where('source', $this->sourceFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        return $query->orderByDesc('created_at')->paginate(20);
    }

    #[Computed]
    public function statusCounts(): array
    {
        $base = app(GrnQueryService::class)->queryFor(auth()->user());

        $counts = ['all' => (clone $base)->count()];

        foreach (GrnStatus::cases() as $status) {
            $counts[$status->value] = (clone $base)->where('status', $status->value)->count();
        }

        return $counts;
    }

    #[Computed]
    public function sources(): array
    {
        return GrnSource::cases();
    }

    #[Computed]
    public function statuses(): array
    {
        return GrnStatus::cases();
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.inbound.grn.index')
            ->layout('components.layouts.app', [
                'title'           => 'Goods Receipt Notes',
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
