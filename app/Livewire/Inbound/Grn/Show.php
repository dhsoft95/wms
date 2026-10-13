<?php

namespace App\Livewire\Inbound\Grn;

use App\Enums\GrnStatus;
use App\Models\Grn;
use App\Services\Inbound\GrnWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public Grn $grn;

    public string $activeTab = 'lines';

    // Variance approval inputs
    public string $approvalNotes = '';

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function lines(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->grn->lines()->with(['item', 'uom', 'varianceReasonCode'])->get();
    }

    #[Computed]
    public function receipts(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->grn->receipts()->with(['line.item', 'uom', 'receivedBy', 'lpn'])->latest()->get();
    }

    #[Computed]
    public function lpns(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->grn->lpns()->with('currentLocation')->get();
    }

    #[Computed]
    public function putawayTasks(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->grn->putawayTasks()
            ->with(['item', 'fromLocation', 'suggestedLocation', 'actualLocation', 'assignedTo', 'completedBy'])
            ->get();
    }

    #[Computed]
    public function varianceApprovals(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->grn->varianceApprovals()->with(['requestedBy', 'decidedBy'])->latest()->get();
    }

    #[Computed]
    public function activityLog(): \Illuminate\Database\Eloquent\Collection
    {
        return activity()
            ->performedOn($this->grn)
            ->latest()
            ->take(50)
            ->get();
    }

    #[Computed]
    public function stepperStatuses(): array
    {
        return [
            GrnStatus::Draft,
            GrnStatus::Receiving,
            GrnStatus::PendingVarianceApproval,
            GrnStatus::Received,
            GrnStatus::PutawayInProgress,
            GrnStatus::PutawayComplete,
            GrnStatus::Closed,
        ];
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function startReceiving(): void
    {
        $this->authorize('receive', $this->grn);

        app(GrnWorkflow::class)->startReceiving($this->grn, auth()->user());
        $this->grn->refresh();
        $this->dispatch('toast', message: 'Receiving started.', type: 'success');
    }

    public function approveVariance(): void
    {
        $this->authorize('approveVariance', $this->grn);

        app(GrnWorkflow::class)->approveVariance($this->grn, auth()->user(), $this->approvalNotes);
        $this->grn->refresh();
        $this->approvalNotes = '';
        $this->dispatch('toast', message: 'Variance approved — GRN confirmed.', type: 'success');
    }

    public function rejectVariance(): void
    {
        $this->authorize('approveVariance', $this->grn);

        app(GrnWorkflow::class)->rejectVariance($this->grn, auth()->user(), $this->approvalNotes);
        $this->grn->refresh();
        $this->approvalNotes = '';
        $this->dispatch('toast', message: 'Variance rejected — returned to receiving.', type: 'info');
    }

    public function cancel(): void
    {
        $this->authorize('cancel', $this->grn);

        app(GrnWorkflow::class)->cancel($this->grn, 'Cancelled by user.', auth()->user());
        $this->grn->refresh();
        $this->dispatch('toast', message: 'GRN cancelled.', type: 'info');
    }

    public function close(): void
    {
        $this->authorize('close', $this->grn);

        app(GrnWorkflow::class)->close($this->grn, auth()->user());
        $this->grn->refresh();
        $this->dispatch('toast', message: 'GRN closed.', type: 'success');
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.inbound.grn.show')
            ->layout('components.layouts.app', [
                'title'           => $this->grn->number,
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
