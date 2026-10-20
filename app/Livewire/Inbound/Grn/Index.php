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

    public function applySearch(string $value): void
    {
        $this->search = $value;
        $this->resetPage();
        unset($this->searchSuggestions);
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function grns(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = app(GrnQueryService::class)
            ->queryFor(auth()->user())
            ->with(['warehouse', 'supplierPartner', 'container', 'purchaseOrder'])
            ->withCount('lines')
            ->where('status', '!=', GrnStatus::Cancelled->value);

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
        $base = app(GrnQueryService::class)->queryFor(auth()->user())
            ->where('status', '!=', GrnStatus::Cancelled->value);

        $counts = ['all' => (clone $base)->count()];

        foreach (GrnStatus::cases() as $status) {
            $counts[$status->value] = (clone $base)->where('status', $status->value)->count();
        }

        return $counts;
    }

    /**
     * Live search suggestions: GRN numbers, supplier names, container numbers.
     *
     * @return array<int, array{label: string, sub: string, value: string}>
     */
    #[Computed]
    public function searchSuggestions(): array
    {
        if (strlen($this->search) < 2) {
            return [];
        }

        $like  = '%' . $this->search . '%';
        $base  = app(GrnQueryService::class)->queryFor(auth()->user());
        $items = [];

        // GRN numbers
        (clone $base)
            ->where('number', 'like', $like)
            ->limit(5)
            ->pluck('number')
            ->each(fn ($n) => $items[] = ['label' => $n, 'sub' => 'GRN', 'value' => $n]);

        // Supplier names via relation
        (clone $base)
            ->whereHas('supplierPartner', fn ($q) => $q->where('name', 'like', $like))
            ->with('supplierPartner:id,name')
            ->limit(4)
            ->get(['id', 'supplier_partner_id'])
            ->each(function ($g) use (&$items) {
                $name = $g->supplierPartner?->name;
                if ($name && ! collect($items)->contains('value', $name)) {
                    $items[] = ['label' => $name, 'sub' => 'Supplier', 'value' => $name];
                }
            });

        // Container numbers
        (clone $base)
            ->whereHas('container', fn ($q) => $q->where('number', 'like', $like))
            ->with('container:id,number')
            ->limit(4)
            ->get(['id', 'container_id'])
            ->each(function ($g) use (&$items) {
                $num = $g->container?->number;
                if ($num && ! collect($items)->contains('value', $num)) {
                    $items[] = ['label' => $num, 'sub' => 'Container', 'value' => $num];
                }
            });

        return array_slice($items, 0, 10);
    }

    #[Computed]
    public function sources(): array
    {
        return GrnSource::cases();
    }

    #[Computed]
    public function statuses(): array
    {
        return array_values(array_filter(
            GrnStatus::cases(),
            fn (GrnStatus $s) => ! in_array($s, [
                GrnStatus::Receiving,
                GrnStatus::PutawayComplete,
                GrnStatus::PendingVarianceApproval,
                GrnStatus::PendingVarianceApproval,
                GrnStatus::Cancelled,
            ], true),
        ));
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
