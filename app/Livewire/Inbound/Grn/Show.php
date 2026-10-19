<?php

namespace App\Livewire\Inbound\Grn;

use App\Enums\GrnStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Models\Grn;
use App\Models\Location;
use App\Models\PutawayTask;
use App\Services\Inbound\GrnWorkflow;
use App\Services\Inbound\PutawayEngine;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public Grn $grn;

    public string $activeTab = 'lines';

    // Variance approval inputs
    public string $approvalNotes = '';

    // Putaway execute state
    public bool    $showExecuteModal = false;
    public ?int    $executingTaskId  = null;
    public string  $destinationId    = '';
    public string  $scanDestination  = '';

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
        return $this->grn->activities()->latest()->take(50)->get();
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

    #[Computed]
    public function executingTask(): ?PutawayTask
    {
        if ($this->executingTaskId === null) {
            return null;
        }

        return PutawayTask::with(['item', 'fromLocation', 'suggestedLocation'])->find($this->executingTaskId);
    }

    #[Computed]
    public function binSearchResults(): array
    {
        if (strlen($this->scanDestination) < 2) {
            return [];
        }

        return Location::where('warehouse_id', $this->grn->warehouse_id)
            ->where('level', LocationLevel::Bin->value)
            ->where('type', LocationType::Storage->value)
            ->where('status', LocationStatus::Active->value)
            ->where(fn ($q) => $q
                ->where('code', 'like', "%{$this->scanDestination}%")
                ->orWhere('barcode', 'like', "%{$this->scanDestination}%")
            )
            ->orderBy('code')
            ->limit(8)
            ->get(['id', 'code', 'barcode'])
            ->map(fn ($l) => ['id' => $l->id, 'code' => $l->code, 'barcode' => $l->barcode])
            ->all();
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
            $this->dispatch('toast', message: 'Variance approved — GRN confirmed.', type: 'success');
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

    // ─── Putaway Execute ──────────────────────────────────────────────────────

    public function openExecute(int $taskId): void
    {
        $this->executingTaskId = $taskId;
        $this->destinationId   = '';
        $this->scanDestination = '';
        $this->showExecuteModal = true;
        unset($this->executingTask, $this->binSearchResults);
    }

    public function closeExecute(): void
    {
        $this->showExecuteModal = false;
        $this->executingTaskId  = null;
        $this->destinationId    = '';
        $this->scanDestination  = '';
        unset($this->executingTask, $this->binSearchResults);
    }

    public function resolveDestination(): void
    {
        $loc = Location::where('warehouse_id', $this->grn->warehouse_id)
            ->where(fn ($q) => $q
                ->where('code', $this->scanDestination)
                ->orWhere('barcode', $this->scanDestination)
            )
            ->first();

        if ($loc) {
            $this->destinationId   = (string) $loc->id;
            $this->scanDestination = $loc->code;
            unset($this->binSearchResults);
        } else {
            $this->dispatch('toast', message: "Location not found: {$this->scanDestination}", type: 'error');
        }
    }

    public function selectBin(int $id, string $code): void
    {
        $this->destinationId   = (string) $id;
        $this->scanDestination = $code;
        unset($this->binSearchResults);
    }

    public function executeTask(): void
    {
        $this->validate(['destinationId' => ['required', 'exists:locations,id']]);

        try {
            $task        = PutawayTask::findOrFail($this->executingTaskId);
            $destination = Location::findOrFail((int) $this->destinationId);

            app(PutawayEngine::class)->execute($task, $destination, auth()->user());

            $this->grn->refresh();
            unset($this->putawayTasks);
            $this->closeExecute();

            $this->dispatch('toast', message: 'Putaway complete!', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Error: ' . $e->getMessage(), type: 'error');
        }
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
