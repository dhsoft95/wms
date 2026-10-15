<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Enums\ContainerStatus;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\PickListStatus;
use App\Models\DeliveryOrder;
use App\Models\Dispatch;
use App\Models\PickList;
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

    #[Computed]
    public function pendingOrdersCount(): int
    {
        return DeliveryOrder::whereIn('status', [
            DeliveryOrderStatus::Draft->value,
            DeliveryOrderStatus::Submitted->value,
            DeliveryOrderStatus::Approved->value,
        ])->count();
    }

    #[Computed]
    public function todayDispatchCount(): int
    {
        return Dispatch::whereDate('dispatched_at', today())->count();
    }

    #[Computed]
    public function openPickListsCount(): int
    {
        return PickList::whereIn('status', [
            PickListStatus::Open->value,
            PickListStatus::InProgress->value,
        ])->count();
    }

    #[Computed]
    public function overdueOrdersCount(): int
    {
        return DeliveryOrder::whereDate('required_date', '<', today())
            ->whereNotIn('status', [
                DeliveryOrderStatus::Dispatched->value,
                DeliveryOrderStatus::Delivered->value,
                DeliveryOrderStatus::Cancelled->value,
            ])
            ->count();
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
