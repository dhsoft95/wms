<?php

declare(strict_types=1);

namespace App\Livewire\Scan;

use App\Enums\Outbound\GatePassStatus;
use App\Models\GatePass;
use App\Services\Outbound\DispatchService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class GateCheck extends Component
{
    public string    $step       = 'scan';
    public string    $gpNumber   = '';
    public ?GatePass $gatePass   = null;
    public string    $scanError  = '';

    public function scan(): void
    {
        $this->scanError = '';
        $this->gatePass  = null;

        $gp = GatePass::with(['dispatch.deliveryOrder.consigneePartner', 'dispatch'])
            ->where('number', strtoupper(trim($this->gpNumber)))
            ->first();

        if ($gp === null) {
            $this->scanError = 'Gate pass not found.';
            return;
        }

        if ($gp->status === GatePassStatus::Exited) {
            $this->scanError = "Gate pass {$gp->number} has already exited.";
            return;
        }

        $this->gatePass = $gp;
        $this->step     = 'confirm';
    }

    public function confirmExit(): void
    {
        if ($this->gatePass === null) {
            return;
        }

        app(DispatchService::class)->verifyGateExit($this->gatePass, auth()->user());

        $this->step = 'done';
    }

    public function startOver(): void
    {
        $this->step      = 'scan';
        $this->gpNumber  = '';
        $this->gatePass  = null;
        $this->scanError = '';
    }

    public function render(): View
    {
        return view('livewire.scan.gate-check')
            ->layout('components.layouts.scan', ['title' => 'Gate Check']);
    }
}
