<?php

declare(strict_types=1);

namespace App\Livewire\Inbound\Containers;

use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\Setting;
use App\Services\Inbound\ContainerQueryService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $statusFilter = 'all';
    public string $filterLine   = '';
    public string $filterDwell  = '';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingFilterLine(): void { $this->resetPage(); }
    public function updatingFilterDwell(): void { $this->resetPage(); }

    private function queryService(): ContainerQueryService
    {
        return app(ContainerQueryService::class);
    }

    #[Computed]
    public function statusCounts(): array
    {
        $base   = $this->queryService()->queryFor(auth()->user());
        $counts = ['all' => $base->count()];
        foreach (ContainerStatus::cases() as $s) {
            $counts[$s->value] = (clone $base)->where('status', $s->value)->count();
        }
        return $counts;
    }

    #[Computed]
    public function containers()
    {
        $query = $this->queryService()->queryFor(auth()->user())
            ->with(['yardLocation', 'shippingLinePartner', 'expectedLines'])
            ->orderByDesc('id');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search !== '') {
            $q = $this->search;
            $query->where(function ($sq) use ($q) {
                $sq->where('container_no', 'like', "%$q%")
                   ->orWhere('number', 'like', "%$q%")
                   ->orWhere('bl_no', 'like', "%$q%")
                   ->orWhere('vessel', 'like', "%$q%");
            });
        }

        if ($this->filterLine !== '') {
            $query->where('shipping_line_partner_id', $this->filterLine);
        }

        if ($this->filterDwell !== '') {
            $warnDays     = (int) Setting::get('container_dwell_warning_days', 7);
            $criticalDays = (int) Setting::get('container_dwell_critical_days', 14);
            $query->whereNotNull('arrived_at')
                  ->whereIn('status', ['arrived', 'parked', 'unloading', 'unloaded']);

            if ($this->filterDwell === 'warning') {
                $query->whereRaw('DATEDIFF(COALESCE(released_at, NOW()), arrived_at) >= ?', [$warnDays])
                      ->whereRaw('DATEDIFF(COALESCE(released_at, NOW()), arrived_at) < ?', [$criticalDays]);
            } elseif ($this->filterDwell === 'critical') {
                $query->whereRaw('DATEDIFF(COALESCE(released_at, NOW()), arrived_at) >= ?', [$criticalDays]);
            }
        }

        return $query->paginate(25);
    }

    public function render()
    {
        return view('livewire.inbound.containers.index')
            ->layout('components.layouts.app', [
                'title'           => 'Containers',
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
