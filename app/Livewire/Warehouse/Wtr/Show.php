<?php

declare(strict_types=1);

namespace App\Livewire\Warehouse\Wtr;

use App\Models\WarehouseTransfer;
use App\Services\Transfers\WarehouseTransferWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public WarehouseTransfer $wtr;
    public bool              $showRejectModal = false;
    public string            $rejectionReason = '';

    public function submit(WarehouseTransferWorkflow $workflow): void
    {
        $this->authorize('submit', $this->wtr);

        try {
            $workflow->submit($this->wtr, auth()->user());
            $this->wtr->refresh();
            $this->dispatch('toast', message: "Transfer {$this->wtr->number} submitted", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function accept(WarehouseTransferWorkflow $workflow): void
    {
        $this->authorize('accept', $this->wtr);

        try {
            $workflow->accept($this->wtr, auth()->user());
            $this->wtr->refresh();
            $this->dispatch('toast', message: "Transfer {$this->wtr->number} accepted", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openRejectModal(): void
    {
        $this->showRejectModal = true;
        $this->rejectionReason = '';
    }

    public function reject(WarehouseTransferWorkflow $workflow): void
    {
        $this->authorize('reject', $this->wtr);
        $this->validate(['rejectionReason' => 'required|string|min:3']);

        try {
            $workflow->reject($this->wtr, $this->rejectionReason, auth()->user());
            $this->wtr->refresh();
            $this->showRejectModal = false;
            $this->dispatch('toast', message: 'Transfer rejected', type: 'info');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function confirmDispatch(WarehouseTransferWorkflow $workflow): void
    {
        $this->authorize('dispatch', $this->wtr);

        $this->wtr->load('lines');

        $lineData = $this->wtr->lines->map(fn ($line) => [
            'source_location_id' => null,
        ])->toArray();

        try {
            $workflow->dispatch($this->wtr, ['lines' => $lineData], auth()->user());
            $this->wtr->refresh();
            $this->dispatch('toast', message: "Transfer {$this->wtr->number} dispatched", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function receive(WarehouseTransferWorkflow $workflow): void
    {
        $this->authorize('receive', $this->wtr);

        try {
            $workflow->receive($this->wtr, auth()->user());
            $this->wtr->refresh();
            $this->dispatch('toast', message: "Transfer {$this->wtr->number} received and completed", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function cancel(WarehouseTransferWorkflow $workflow): void
    {
        $this->authorize('cancel', $this->wtr);

        try {
            $workflow->cancel($this->wtr, 'Cancelled by user', auth()->user());
            $this->wtr->refresh();
            $this->dispatch('toast', message: "Transfer {$this->wtr->number} cancelled", type: 'info');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        $this->wtr->load(
            'lines.item', 'lines.uom',
            'fromWarehouse', 'toWarehouse',
            'fromVirtualWarehouse', 'toVirtualWarehouse',
            'createdBy', 'acceptedBy',
            'purchaseOrder',
        );

        return view('livewire.warehouse.wtr.show')
            ->layout('components.layouts.app', [
                'title'           => $this->wtr->number,
                'breadcrumbGroup' => 'Operations',
            ]);
    }
}
