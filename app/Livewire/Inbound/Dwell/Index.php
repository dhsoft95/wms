<?php

declare(strict_types=1);

namespace App\Livewire\Inbound\Dwell;

use App\Models\Setting;
use App\Services\Inbound\ContainerQueryService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    #[Computed]
    public function stats(): array
    {
        $user         = auth()->user();
        $queryService = app(ContainerQueryService::class);

        $containers = $queryService->queryFor($user)
            ->whereNotNull('arrived_at')
            ->whereIn('status', ['arrived', 'parked', 'unloading', 'unloaded'])
            ->with(['yardLocation'])
            ->get();

        $warnDays     = (int) Setting::get('container_dwell_warning_days', 7);
        $criticalDays = (int) Setting::get('container_dwell_critical_days', 14);

        $inYard         = $containers->count();
        $avgDwell       = $inYard > 0 ? round($containers->avg(fn($c) => $c->dwellDays())) : 0;
        $overWarning    = $containers->filter(fn($c) => $c->dwellDays() >= $warnDays)->count();
        $overCritical   = $containers->filter(fn($c) => $c->dwellDays() >= $criticalDays)->count();
        $totalDemurrage = $containers->sum(fn($c) => $c->demurrageDays());

        return compact('inYard', 'avgDwell', 'overWarning', 'overCritical', 'totalDemurrage');
    }

    #[Computed]
    public function containers()
    {
        $user         = auth()->user();
        $queryService = app(ContainerQueryService::class);

        return $queryService->queryFor($user)
            ->whereNotNull('arrived_at')
            ->whereIn('status', ['arrived', 'parked', 'unloading', 'unloaded'])
            ->with(['yardLocation', 'shippingLinePartner'])
            ->get()
            ->sortByDesc(fn($c) => $c->dwellDays())
            ->values();
    }

    public function render()
    {
        return view('livewire.inbound.dwell.index')
            ->layout('components.layouts.app', [
                'title'           => 'Dwell Time',
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
