<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\Dispatch;

use App\Models\Dispatch;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public Dispatch $dispatch;

    public function mount(Dispatch $dispatch): void
    {
        $this->authorize('dispatch.view');
    }

    #[Computed]
    public function record(): Dispatch
    {
        return $this->dispatch->load([
            'deliveryOrder.consigneePartner',
            'deliveryOrder.virtualWarehouse',
            'transporterPartner',
            'dispatchedBy',
            'gatePass.verifiedBy',
            'items.item',
            'items.batch',
        ]);
    }

    public function render(): View
    {
        return view('livewire.outbound.dispatch.show')
            ->layout('components.layouts.app', [
                'title'           => $this->dispatch->number,
                'breadcrumbGroup' => 'Outbound',
            ]);
    }
}
