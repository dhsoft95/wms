<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Enums\ContainerStatus;
use App\Services\Inbound\ContainerQueryService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Overview extends Component
{
    #[Computed]
    public function activeContainersCount(): int
    {
        $activeValues = array_column(
            array_filter(ContainerStatus::cases(), fn($s) => $s->isActive()),
            'value'
        );

        return app(ContainerQueryService::class)
            ->queryFor(auth()->user())
            ->whereIn('status', $activeValues)
            ->count();
    }

    #[Computed]
    public function arrivingThisWeek(): int
    {
        return app(ContainerQueryService::class)
            ->queryFor(auth()->user())
            ->where('status', ContainerStatus::Expected->value)
            ->whereBetween('eta', [now(), now()->endOfWeek()])
            ->count();
    }

    #[Computed]
    public function pipelineContainers()
    {
        return app(ContainerQueryService::class)
            ->queryFor(auth()->user())
            ->whereIn('status', ['expected', 'arrived', 'parked', 'unloading'])
            ->with('yardLocation')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.dashboard.overview')
            ->layout('components.layouts.app', [
                'title'           => 'Operations Overview',
                'breadcrumbGroup' => 'Overview',
            ]);
    }
}
