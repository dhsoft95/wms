<?php

declare(strict_types=1);

namespace App\Livewire\Warehouse\Transfers;

use App\Models\StockTransfer;
use App\Services\Inventory\TransferWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public StockTransfer $transfer;
    public bool          $showRejectModal   = false;
    public string        $rejectionReason   = '';
    public bool          $showReturnModal   = false;
    public string        $returnNotes       = '';
    public bool          $showForwardModal  = false;

    public function submit(TransferWorkflow $workflow): void
    {
        $this->authorize('submit', $this->transfer);

        try {
            $this->transfer = $workflow->submit($this->transfer, auth()->user());
            $this->dispatch('toast', message: "Transfer {$this->transfer->number} submitted for approval", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function accept(TransferWorkflow $workflow): void
    {
        $this->authorize('accept', $this->transfer);

        try {
            $this->transfer = $workflow->accept($this->transfer, auth()->user());
            $this->dispatch('toast', message: "Transfer {$this->transfer->number} accepted", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function execute(TransferWorkflow $workflow): void
    {
        $this->authorize('execute', $this->transfer);

        try {
            $this->transfer = $workflow->post($this->transfer, auth()->user());
            $this->dispatch('toast', message: "Transfer {$this->transfer->number} executed and posted", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openRejectModal(): void
    {
        $this->showRejectModal = true;
        $this->rejectionReason = '';
    }

    public function reject(TransferWorkflow $workflow): void
    {
        $this->authorize('reject', $this->transfer);
        $this->validate(['rejectionReason' => 'required|string|min:3']);

        try {
            $this->transfer       = $workflow->reject($this->transfer, auth()->user(), $this->rejectionReason);
            $this->showRejectModal = false;
            $this->dispatch('toast', message: 'Transfer rejected', type: 'info');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function approveDepartment(TransferWorkflow $workflow): void
    {
        $this->authorize('approveDepartment', $this->transfer);

        try {
            $this->transfer = $workflow->approveDepartment($this->transfer, auth()->user());
            $this->dispatch('toast', message: 'Transfer approved', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openReturnModal(): void
    {
        $this->showReturnModal = true;
        $this->returnNotes = '';
    }

    public function sendBack(TransferWorkflow $workflow): void
    {
        $this->authorize('returnOrder', $this->transfer);
        $this->validate(['returnNotes' => 'required|string|min:3']);

        try {
            $this->transfer = $workflow->returnForCorrection($this->transfer, auth()->user(), $this->returnNotes);
            $this->showReturnModal = false;
            $this->dispatch('toast', message: 'Transfer returned for correction', type: 'info');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function forwardReturn(TransferWorkflow $workflow): void
    {
        $this->authorize('forwardReturn', $this->transfer);

        try {
            $this->transfer = $workflow->forwardToRequester($this->transfer, auth()->user());
            $this->dispatch('toast', message: 'Transfer forwarded to requester', type: 'info');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        $this->transfer->load(
            'lines.item', 'lines.location', 'lines.toLocation', 'lines.batch', 'lines.uom',
            'createdBy', 'acceptedBy', 'fromVirtualWarehouse', 'toVirtualWarehouse',
            'approvals.decidedBy', 'approvals.approvalChain',
        );

        return view('livewire.warehouse.transfers.show')
            ->layout('components.layouts.app', [
                'title'           => $this->transfer->number,
                'breadcrumbGroup' => 'Warehouse Operations',
            ]);
    }
}
