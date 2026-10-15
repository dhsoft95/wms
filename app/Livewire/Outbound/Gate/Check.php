<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\Gate;

use App\Enums\Outbound\GatePassStatus;
use App\Models\GatePass;
use App\Services\Outbound\DispatchService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Check extends Component
{
    public string $gatePassNumber = '';
    public ?GatePass $found       = null;
    public string $scanError      = '';

    public function search(): void
    {
        $this->scanError = '';
        $this->found     = null;

        $gp = GatePass::with(['dispatch.deliveryOrder.consigneePartner', 'dispatch.items.item'])
            ->where('number', strtoupper(trim($this->gatePassNumber)))
            ->first();

        if ($gp === null) {
            $this->scanError = 'Gate pass not found.';
            return;
        }

        $this->found = $gp;
    }

    public function verifyExit(): void
    {
        $this->authorize('gate.verify');

        if ($this->found === null) {
            return;
        }

        if ($this->found->status === GatePassStatus::Exited) {
            $this->scanError = 'Gate pass has already exited.';
            return;
        }

        app(DispatchService::class)->verifyGateExit($this->found, auth()->user());

        $this->dispatch('toast', message: "Gate exit verified for {$this->found->number}.", type: 'success');
        $this->found            = $this->found->fresh(['dispatch.deliveryOrder.consigneePartner']);
        $this->gatePassNumber   = '';
    }

    public function render(): View
    {
        return view('livewire.outbound.gate.check')
            ->layout('components.layouts.app', [
                'title'           => 'Gate Check',
                'breadcrumbGroup' => 'Outbound',
            ]);
    }
}
