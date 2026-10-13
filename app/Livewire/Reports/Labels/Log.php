<?php

declare(strict_types=1);

namespace App\Livewire\Reports\Labels;

use App\Enums\LabelTemplateType;
use App\Models\LabelPrintJob;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Log extends Component
{
    use WithPagination;

    public string $filterType    = '';
    public string $filterReprint = '';
    public string $filterUser    = '';
    public string $dateFrom      = '';
    public string $dateTo        = '';

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(): void
    {
        if (! auth()->user()->can('labels.log.view')) {
            abort(403);
        }
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function updatingFilterReprint(): void
    {
        $this->resetPage();
    }

    public function updatingFilterUser(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function jobs(): LengthAwarePaginator
    {
        return LabelPrintJob::with(['labelTemplate', 'printer', 'printedBy'])
            ->when($this->filterType !== '', function ($q) {
                $q->whereHas('labelTemplate', fn ($t) => $t->where('type', $this->filterType));
            })
            ->when($this->filterReprint !== '', function ($q) {
                $q->where('is_reprint', true);
            })
            ->when($this->filterUser !== '', function ($q) {
                $q->whereHas('printedBy', function ($u) {
                    $u->where('name', 'like', "%{$this->filterUser}%")
                      ->orWhere('email', 'like', "%{$this->filterUser}%");
                });
            })
            ->when($this->dateFrom !== '', function ($q) {
                $q->whereDate('created_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo !== '', function ($q) {
                $q->whereDate('created_at', '<=', $this->dateTo);
            })
            ->orderByDesc('created_at')
            ->paginate(25);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.reports.labels.log', [
            'templateTypes' => LabelTemplateType::cases(),
        ])
            ->layout('components.layouts.app', [
                'title'           => 'Label Print Log',
                'breadcrumbGroup' => 'Reports & Documents',
            ]);
    }
}
