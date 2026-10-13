<?php

namespace App\Livewire\Warehouse\Putaway;

use App\Enums\PutawayTaskStatus;
use App\Models\Location;
use App\Models\PutawayTask;
use App\Models\User;
use App\Services\Inbound\PutawayEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $statusFilter = 'all';
    public bool   $myTasksOnly  = false;
    public string $search       = '';

    // Bulk assign modal
    public bool  $showBulkAssignModal = false;
    public array $selectedTaskIds     = [];
    public string $assignToUserId     = '';

    // Execution modal
    public bool   $showExecuteModal  = false;
    public ?int   $executingTaskId   = null;
    public string $scanDestination   = '';
    public string $destinationId     = '';

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedMyTasksOnly(): void   { $this->resetPage(); }
    public function updatedSearch(): void        { $this->resetPage(); }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function tasks(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = PutawayTask::with(['grn', 'item', 'fromLocation', 'suggestedLocation', 'actualLocation', 'assignedTo'])
            ->whereHas('grn'); // must be linked to a GRN

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->myTasksOnly) {
            $query->where('assigned_to', auth()->id());
        }

        if ($this->search !== '') {
            $search = '%' . $this->search . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('item', fn ($sq) => $sq->where('sku', 'like', $search)->orWhere('name', 'like', $search))
                  ->orWhereHas('grn', fn ($gq) => $gq->where('number', 'like', $search));
            });
        }

        return $query->orderByDesc('created_at')->paginate(20);
    }

    #[Computed]
    public function statusCounts(): array
    {
        $base   = PutawayTask::whereHas('grn');
        $counts = ['all' => (clone $base)->count()];

        foreach (PutawayTaskStatus::cases() as $status) {
            $counts[$status->value] = (clone $base)->where('status', $status->value)->count();
        }

        return $counts;
    }

    #[Computed]
    public function statuses(): array
    {
        return PutawayTaskStatus::cases();
    }

    #[Computed]
    public function users(): \Illuminate\Database\Eloquent\Collection
    {
        return User::active()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['storekeeper', 'scanner-operator', 'warehouse-manager']))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function executingTask(): ?PutawayTask
    {
        if ($this->executingTaskId === null) {
            return null;
        }
        return PutawayTask::with(['item', 'fromLocation', 'suggestedLocation', 'grn'])->find($this->executingTaskId);
    }

    #[Computed]
    public function suggestedLocation(): ?Location
    {
        $task = $this->executingTask;
        if ($task === null) {
            return null;
        }

        if ($this->destinationId !== '') {
            return Location::find((int) $this->destinationId);
        }

        return $task->suggestedLocation;
    }

    // ─── Bulk Assign ──────────────────────────────────────────────────────────

    public function openBulkAssign(): void
    {
        $this->showBulkAssignModal = true;
    }

    public function closeBulkAssign(): void
    {
        $this->showBulkAssignModal = false;
        $this->selectedTaskIds    = [];
        $this->assignToUserId     = '';
    }

    public function bulkAssign(): void
    {
        $this->validate([
            'assignToUserId'   => ['required', 'exists:users,id'],
            'selectedTaskIds'  => ['required', 'array', 'min:1'],
        ]);

        $assignee = User::findOrFail((int) $this->assignToUserId);
        $tasks    = PutawayTask::whereIn('id', $this->selectedTaskIds)->get();

        $count = app(PutawayEngine::class)->bulkAssign($tasks, $assignee, auth()->user());
        $this->closeBulkAssign();
        $this->dispatch('toast', message: "{$count} task(s) assigned to {$assignee->name}.", type: 'success');
    }

    // ─── Execute ──────────────────────────────────────────────────────────────

    public function openExecute(int $taskId): void
    {
        $this->executingTaskId = $taskId;
        $this->showExecuteModal = true;
        $this->scanDestination = '';
        $this->destinationId   = '';
    }

    public function closeExecute(): void
    {
        $this->showExecuteModal = false;
        $this->executingTaskId  = null;
        $this->scanDestination  = '';
        $this->destinationId    = '';
    }

    public function resolveDestination(): void
    {
        $code = trim($this->scanDestination);
        if ($code === '') {
            return;
        }

        $location = Location::where('code', $code)
            ->orWhere('barcode', $code)
            ->where('type', 'storage')
            ->where('status', 'active')
            ->first();

        if ($location) {
            $this->destinationId   = (string) $location->id;
            $this->scanDestination = $location->code;
            $this->dispatch('toast', message: "Location found: {$location->code}", type: 'info');
        } else {
            $this->dispatch('toast', message: "Location not found: {$code}", type: 'error');
        }
    }

    public function executeTask(): void
    {
        $this->authorize('execute', PutawayTask::findOrFail($this->executingTaskId));

        $this->validate([
            'destinationId' => ['required', 'exists:locations,id'],
        ]);

        try {
            $task        = PutawayTask::findOrFail($this->executingTaskId);
            $destination = Location::findOrFail((int) $this->destinationId);

            app(PutawayEngine::class)->execute($task, $destination, auth()->user());
            $this->closeExecute();
            $this->dispatch('toast', message: 'Putaway complete!', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Error: ' . $e->getMessage(), type: 'error');
        }
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.warehouse.putaway.index')
            ->layout('components.layouts.app', [
                'title'           => 'Put-Away',
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
