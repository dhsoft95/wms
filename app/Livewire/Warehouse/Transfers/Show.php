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
    public bool          $showRejectModal  = false;
    public string        $rejectionReason  = '';

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

    public function approve(TransferWorkflow $workflow): void
    {
        $this->authorize('approve', $this->transfer);

        try {
            $this->transfer = $workflow->approve($this->transfer, auth()->user());
            $this->dispatch('toast', message: "Transfer {$this->transfer->number} approved and posted", type: 'success');
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

    public function render(): View
    {
        $this->transfer->load(
            'lines.item', 'lines.location', 'lines.batch', 'lines.uom',
            'createdBy', 'approvedBy', 'fromVirtualWarehouse', 'toVirtualWarehouse'
        );

        return view('livewire.warehouse.transfers.show')
            ->layout('components.layouts.app', [
                'title'           => $this->transfer->number,
                'breadcrumbGroup' => 'Warehouse Operations',
            ]);
    }
}
