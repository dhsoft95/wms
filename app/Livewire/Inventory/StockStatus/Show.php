<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\StockStatus;

use App\Enums\Inventory\StockStatusRequestStatus;
use App\Models\StockStatusRequest;
use App\Services\Inventory\StockStatusRequestWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public StockStatusRequest $stockStatusRequest;

    public bool   $showApproveModal = false;
    public bool   $showRejectModal  = false;
    public string $approveComment   = '';
    public string $rejectReason     = '';

    public function mount(StockStatusRequest $stockStatusRequest): void
    {
        $this->stockStatusRequest = $stockStatusRequest;
    }

    public function openApproveModal(): void { $this->showApproveModal = true; }
    public function closeApproveModal(): void { $this->showApproveModal = false; }

    public function approve(StockStatusRequestWorkflow $workflow): void
    {
        $this->authorize('approve', $this->stockStatusRequest);

        try {
            $workflow->approve($this->stockStatusRequest, auth()->user(), $this->approveComment ?: null);
            $this->stockStatusRequest->refresh();
            $this->showApproveModal = false;
            $this->dispatch('toast', message: 'Stock status request approved.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openRejectModal(): void { $this->showRejectModal = true; }
    public function closeRejectModal(): void { $this->showRejectModal = false; }

    public function reject(StockStatusRequestWorkflow $workflow): void
    {
        $this->validate(['rejectReason' => ['required', 'string', 'min:5']]);
        $this->authorize('reject', $this->stockStatusRequest);

        try {
            $workflow->reject($this->stockStatusRequest, auth()->user(), $this->rejectReason);
            $this->stockStatusRequest->refresh();
            $this->showRejectModal = false;
            $this->dispatch('toast', message: 'Stock status request rejected.', type: 'info');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        $this->stockStatusRequest->loadMissing([
            'lines.item.baseUom',
            'lines.batch',
            'lines.location',
            'createdBy',
            'approvedBy',
            'rejectedBy',
            'virtualWarehouse',
            'department',
            'approvals.user',
        ]);

        return view('livewire.inventory.stock-status.show')
            ->layout('components.layouts.app', [
                'title'           => "SSR {$this->stockStatusRequest->number}",
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
