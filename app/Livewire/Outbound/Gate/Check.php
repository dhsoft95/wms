<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\Gate;

use App\Enums\Outbound\GatePassStatus;
use App\Enums\ScanResultType;
use App\Livewire\Concerns\HandlesScans;
use App\Models\Dispatch as DispatchModel;
use App\Models\GatePass;
use App\Services\Outbound\DispatchService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Check extends Component
{
    use HandlesScans;

    public string $gatePassNumber = '';
    public ?GatePass $found       = null;
    public string $scanError      = '';

    public function processScan(): void
    {
        $result = $this->resolveScan();
        if ($result === null) return;

        if (! $result->ok()) {
            $this->scanError = $result->error ?? 'Barcode not recognised.';
            $this->scanFail($this->scanError);
            return;
        }

        match ($result->type) {
            ScanResultType::GatePass => $this->loadGatePass($result->record),
            ScanResultType::Dispatch => $this->loadGatePassByDispatch($result->record),
            default                  => $this->scanFail('Not a gate pass or dispatch barcode.'),
        };
    }

    private function loadGatePass(?GatePass $gp): void
    {
        if (! $gp) {
            $this->scanFail('Gate pass not found.');
            return;
        }
        $this->found     = $gp->load(['dispatch.deliveryOrder.consigneePartner', 'dispatch.items.item']);
        $this->scanError = '';
        $this->scanOk("Gate pass {$gp->number} found.");
    }

    private function loadGatePassByDispatch(?DispatchModel $dispatch): void
    {
        if (! $dispatch) {
            $this->scanFail('Dispatch not found.');
            return;
        }
        $gp = GatePass::with(['dispatch.deliveryOrder.consigneePartner', 'dispatch.items.item'])
            ->where('dispatch_id', $dispatch->id)
            ->first();
        if (! $gp) {
            $this->scanFail("No gate pass for dispatch {$dispatch->number}.");
            return;
        }
        $this->found     = $gp;
        $this->scanError = '';
        $this->scanOk("Gate pass {$gp->number} found.");
    }

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
