<?php

namespace App\Livewire\Warehouse\Putaway;

use App\Enums\PutawayTaskStatus;
use App\Enums\ScanResultType;
use App\Livewire\Concerns\HasPutawayModal;
use App\Models\PutawayTask;
use App\Models\User;
use App\Services\Scanning\BarcodeResolver;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    use HasPutawayModal;

    public string $statusFilter = 'all';
    public bool   $myTasksOnly  = false;
    public string $search       = '';
    public string $scanInput    = '';

    // Bulk assign modal
    public bool   $showBulkAssignModal = false;
    public array  $selectedTaskIds     = [];
    public string $assignToUserId      = '';

    // ─── Scan ─────────────────────────────────────────────────────────────────

    public function processScan(): void
    {
        $input          = trim($this->scanInput);
        $this->scanInput = '';
        if ($input === '') {
            return;
        }

        $result = app(BarcodeResolver::class)->resolve($input);

        if ($result->type !== ScanResultType::Item) {
            $this->dispatch('scan-error');
            $this->dispatch('toast', message: $result->error ?? 'Not an item barcode.', type: 'error');
            return;
        }

        $task = PutawayTask::where('item_id', $result->record->id)
            ->whereIn('status', [
                PutawayTaskStatus::Open->value,
                PutawayTaskStatus::Assigned->value,
                PutawayTaskStatus::InProgress->value,
            ])
            ->first();

        if (! $task) {
            $this->dispatch('scan-error');
            $this->dispatch('toast', message: 'No open putaway task found for this barcode.', type: 'error');
            return;
        }

        $this->openExecute($task->id);
        $this->dispatch('scan-success');
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedMyTasksOnly(): void   { $this->resetPage(); }
    public function updatedSearch(): void        { $this->resetPage(); }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function tasks(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = PutawayTask::with(['grn', 'item', 'fromLocation', 'actualLocation', 'assignedTo'])
            ->whereHas('grn');

        if ($this->statusFilter !== 'all') {
            // in_progress tab groups open + assigned + in_progress (all displayed as "Putaway In Progress")
            if ($this->statusFilter === PutawayTaskStatus::InProgress->value) {
                $query->whereIn('status', [
                    PutawayTaskStatus::Open->value,
                    PutawayTaskStatus::Assigned->value,
                    PutawayTaskStatus::InProgress->value,
                ]);
            } else {
                $query->where('status', $this->statusFilter);
            }
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

        // Group open + assigned + in_progress under the in_progress key
        $counts[PutawayTaskStatus::InProgress->value] = (clone $base)->whereIn('status', [
            PutawayTaskStatus::Open->value,
            PutawayTaskStatus::Assigned->value,
            PutawayTaskStatus::InProgress->value,
        ])->count();

        $counts[PutawayTaskStatus::Completed->value] = (clone $base)
            ->where('status', PutawayTaskStatus::Completed->value)
            ->count();

        return $counts;
    }

    #[Computed]
    public function statuses(): array
    {
        return [
            PutawayTaskStatus::InProgress,
            PutawayTaskStatus::Completed,
        ];
    }

    #[Computed]
    public function users(): \Illuminate\Database\Eloquent\Collection
    {
        return User::where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['storekeeper', 'scanner-operator', 'warehouse-manager']))
            ->orderBy('name')
            ->get();
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
            'assignToUserId'  => ['required', 'exists:users,id'],
            'selectedTaskIds' => ['required', 'array', 'min:1'],
        ]);

        $assignee = User::findOrFail((int) $this->assignToUserId);
        $tasks    = PutawayTask::whereIn('id', $this->selectedTaskIds)->get();

        $count = app(PutawayEngine::class)->bulkAssign($tasks, $assignee, auth()->user());
        $this->closeBulkAssign();
        $this->dispatch('toast', message: "{$count} task(s) assigned to {$assignee->name}.", type: 'success');
    }

    protected function afterPutawayExecuted(): void
    {
        unset($this->tasks);
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
