<?php

namespace App\Livewire\Inbound\Grn;

use App\Enums\GrnStatus;
use App\Livewire\Concerns\HasPutawayModal;
use App\Models\Grn;
use App\Models\PutawayTask;
use App\Services\Inbound\GrnWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    use HasPutawayModal;

    public Grn $grn;

    public string $activeTab = 'lines';

    // Variance approval inputs
    public string $approvalNotes = '';

    // Task details modal state
    public bool $showDetailsModal = false;
    public ?int $detailsTaskId    = null;

    public function mount(): void
    {
        if (in_array($this->grn->status, [GrnStatus::Received, GrnStatus::PutawayInProgress], true)) {
            $this->activeTab = 'putaway';
        }
    }

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
            ->with(['item', 'fromLocation', 'suggestedLocation', 'actualLocation', 'assignedTo', 'completedBy', 'splits.location'])
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
        return $this->grn->activities()->latest()->take(50)->get();
    }

    #[Computed]
    public function stepperStatuses(): array
    {
        return [
            GrnStatus::Draft,
            GrnStatus::Receiving,
            GrnStatus::Received,
            GrnStatus::PutawayInProgress,
            GrnStatus::Closed,
        ];
    }

    /**
     * Maps internal statuses onto the 5-step visible stepper.
     * PutawayComplete stays on the Putaway step until the user explicitly closes.
     */
    public function stepperStatus(): GrnStatus
    {
        return match ($this->grn->status) {
            GrnStatus::PendingVarianceApproval => GrnStatus::Receiving,
            GrnStatus::PutawayComplete         => GrnStatus::PutawayInProgress,
            default                            => $this->grn->status,
        };
    }

    #[Computed]
    public function detailsTask(): ?PutawayTask
    {
        if ($this->detailsTaskId === null) {
            return null;
        }

        return PutawayTask::with([
            'item', 'fromLocation', 'suggestedLocation', 'actualLocation',
            'assignedTo', 'completedBy', 'splits.location', 'lpn',
        ])->find($this->detailsTaskId);
    }

    public function openDetails(int $taskId): void
    {
        $this->detailsTaskId    = $taskId;
        $this->showDetailsModal = true;
        unset($this->detailsTask);
    }

    public function closeDetails(): void
    {
        $this->showDetailsModal = false;
        $this->detailsTaskId    = null;
        unset($this->detailsTask);
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function startReceiving(): void
    {
        $this->authorize('receive', $this->grn);

        try {
            app(GrnWorkflow::class)->startReceiving($this->grn, auth()->user());
            $this->grn->refresh();
            $this->dispatch('toast', message: 'Receiving started.', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function confirmGrn(): void
    {
        $this->authorize('confirm', $this->grn);

        try {
            app(GrnWorkflow::class)->confirm($this->grn, auth()->user());
            $this->grn->refresh();
            $this->activeTab = 'putaway';
            $this->dispatch('toast', message: 'GRN confirmed — stock posted to dock.', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function approveVariance(): void
    {
        $this->authorize('approveVariance', $this->grn);

        try {
            app(GrnWorkflow::class)->approveVariance($this->grn, auth()->user(), $this->approvalNotes);
            $this->grn->refresh();
            $this->approvalNotes = '';
            $this->dispatch('toast', message: 'Variance approved  GRN confirmed.', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function rejectVariance(): void
    {
        $this->authorize('approveVariance', $this->grn);

        try {
            app(GrnWorkflow::class)->rejectVariance($this->grn, auth()->user(), $this->approvalNotes);
            $this->grn->refresh();
            $this->approvalNotes = '';
            $this->dispatch('toast', message: 'Variance rejected — returned to receiving.', type: 'info');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function cancel(): void
    {
        $this->authorize('cancel', $this->grn);

        try {
            app(GrnWorkflow::class)->cancel($this->grn, 'Cancelled by user.', auth()->user());
            $this->grn->refresh();
            $this->dispatch('toast', message: 'GRN cancelled.', type: 'info');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function close(): void
    {
        $this->authorize('close', $this->grn);

        try {
            app(GrnWorkflow::class)->close($this->grn, auth()->user());
            $this->grn->refresh();
            $this->dispatch('toast', message: 'GRN closed.', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    protected function afterPutawayExecuted(): void
    {
        $this->grn->refresh();
        unset($this->putawayTasks);
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
