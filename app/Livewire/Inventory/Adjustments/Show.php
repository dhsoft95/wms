<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\Adjustments;

use App\Enums\AdjustmentStatus;
use App\Models\StockAdjustment;
use App\Services\Inventory\AdjustmentWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public StockAdjustment $adjustment;
    public bool            $showRejectModal = false;
    public string          $rejectionReason = '';

    public function submit(AdjustmentWorkflow $workflow): void
    {
        $this->authorize('submit', $this->adjustment);

        try {
            $this->adjustment = $workflow->submit($this->adjustment, auth()->user());
            $msg = $this->adjustment->status === AdjustmentStatus::Posted
                ? "Adjustment {$this->adjustment->number} auto-posted (below threshold)"
                : "Adjustment {$this->adjustment->number} submitted for approval";
            $this->dispatch('toast', message: $msg, type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function approve(AdjustmentWorkflow $workflow): void
    {
        $this->authorize('approve', $this->adjustment);

        try {
            $this->adjustment = $workflow->approve($this->adjustment, auth()->user());
            $this->dispatch('toast', message: "Adjustment {$this->adjustment->number} approved and posted", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openRejectModal(): void
    {
        $this->showRejectModal = true;
        $this->rejectionReason = '';
    }

    public function reject(AdjustmentWorkflow $workflow): void
    {
        $this->authorize('reject', $this->adjustment);
        $this->validate(['rejectionReason' => 'required|string|min:3']);

        try {
            $this->adjustment     = $workflow->reject($this->adjustment, auth()->user(), $this->rejectionReason);
            $this->showRejectModal = false;
            $this->dispatch('toast', message: "Adjustment rejected", type: 'info');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        $this->adjustment->load('lines.item', 'lines.location', 'lines.batch', 'lines.reasonCode', 'lines.uom', 'createdBy', 'approvedBy', 'virtualWarehouse');

        return view('livewire.inventory.adjustments.show')
            ->layout('components.layouts.app', [
                'title'           => $this->adjustment->number,
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
