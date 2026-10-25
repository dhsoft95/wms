<?php

namespace App\Livewire\Warehouse\Putaway;

use App\Enums\Inventory\SerialStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\PutawayTaskStatus;
use App\Models\Inventory\SerialNumber;
use App\Models\Location;
use App\Models\PutawayTask;
use App\Models\User;
use App\Services\Inbound\PutawayEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $statusFilter = 'all';
    public bool   $myTasksOnly  = false;
    public string $search       = '';

    // Bulk assign modal
    public bool   $showBulkAssignModal = false;
    public array  $selectedTaskIds     = [];
    public string $assignToUserId      = '';

    // Execute modal
    public bool   $showExecuteModal = false;
    public ?int   $executingTaskId  = null;
    public bool   $hasDraft         = false;

    /** @var array<int, array{locationId: string, locationCode: string, qty: string, serials: array<string>}> */
    public array $splitRows = [];

    /** @var array<string> */
    public array $taskSerials = [];

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

    #[Computed]
    public function executingTask(): ?PutawayTask
    {
        if ($this->executingTaskId === null) {
            return null;
        }

        return PutawayTask::with(['item', 'fromLocation', 'grn'])->find($this->executingTaskId);
    }

    // ─── Bin Search ───────────────────────────────────────────────────────────

    #[Renderless]
    public function getBinSuggestions(string $search = ''): array
    {
        $task        = $this->executingTask;
        $warehouseId = $task?->grn?->warehouse_id;
        $isHazardous = (bool) $task?->item?->is_hazardous;

        $allowedTypes = $isHazardous
            ? [LocationType::Hazardous->value]
            : [LocationType::Storage->value, LocationType::Staging->value];

        $query = Location::where('level', LocationLevel::Bin->value)
            ->whereIn('type', $allowedTypes)
            ->where('status', LocationStatus::Active->value);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
            );
        }

        return $query->orderBy('code')
            ->limit(200)
            ->get(['id', 'code', 'barcode'])
            ->map(fn ($l) => ['id' => $l->id, 'code' => $l->code, 'barcode' => $l->barcode])
            ->toArray();
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

    // ─── Execute ──────────────────────────────────────────────────────────────

    public function openExecute(int $taskId): void
    {
        $this->executingTaskId = $taskId;
        unset($this->executingTask);

        $task = PutawayTask::with('item')->find($taskId);

        // Check for saved draft
        $draftKey   = "putaway_draft_{$this->getAuthId()}_{$taskId}";
        $draft      = Cache::get($draftKey);
        $this->hasDraft = $draft !== null;

        if ($draft) {
            $this->splitRows = $draft;
        } else {
            $this->splitRows = [
                [
                    'locationId'   => '',
                    'locationCode' => '',
                    'qty'          => $task ? rtrim(rtrim(number_format((float) $task->qty, 4), '0'), '.') : '',
                    'serials'      => [],
                ],
            ];
        }

        // Load serials for serial-tracked items
        $this->taskSerials = [];
        if ($task?->item?->track_serial) {
            $query = SerialNumber::where('item_id', $task->item_id)
                ->where('location_id', $task->from_location_id)
                ->where('status', SerialStatus::InStock);

            if ($task->batch_id) {
                $query->where('batch_id', $task->batch_id);
            }
            if ($task->lpn_id) {
                $query->where('lpn_id', $task->lpn_id);
            }

            $this->taskSerials = $query->orderBy('serial_no')->limit((int) $task->qty)->pluck('serial_no')->all();
        }

        $this->showExecuteModal = true;
    }

    public function closeExecute(): void
    {
        $this->showExecuteModal = false;
        $this->executingTaskId  = null;
        $this->splitRows        = [];
        $this->taskSerials      = [];
        $this->hasDraft         = false;
        unset($this->executingTask);
    }

    public function addSplitRow(): void
    {
        $this->splitRows[] = [
            'locationId'   => '',
            'locationCode' => '',
            'qty'          => '',
            'serials'      => [],
        ];
    }

    public function removeSplitRow(int $index): void
    {
        array_splice($this->splitRows, $index, 1);
        $this->splitRows = array_values($this->splitRows);
    }

    public function toggleRowSerial(int $rowIndex, string $serial): void
    {
        $serials = $this->splitRows[$rowIndex]['serials'] ?? [];
        $pos     = array_search($serial, $serials, true);

        if ($pos !== false) {
            array_splice($serials, $pos, 1);
        } else {
            $serials[] = $serial;
        }

        $this->splitRows[$rowIndex]['serials'] = array_values($serials);
    }

    public function selectSplitBin(int $rowIndex, int $locationId, string $locationCode): void
    {
        $this->splitRows[$rowIndex]['locationId']   = (string) $locationId;
        $this->splitRows[$rowIndex]['locationCode'] = $locationCode;
    }

    public function clearSplitBin(int $rowIndex): void
    {
        $this->splitRows[$rowIndex]['locationId']   = '';
        $this->splitRows[$rowIndex]['locationCode'] = '';
    }

    public function saveDraft(): void
    {
        if ($this->executingTaskId === null) {
            return;
        }

        $draftKey = "putaway_draft_{$this->getAuthId()}_{$this->executingTaskId}";
        Cache::put($draftKey, $this->splitRows, now()->addDays(7));
        $this->closeExecute();
        $this->dispatch('toast', message: 'Draft saved. You can resume this putaway later.', type: 'info');
    }

    public function discardDraft(): void
    {
        if ($this->executingTaskId === null) {
            return;
        }

        $draftKey = "putaway_draft_{$this->getAuthId()}_{$this->executingTaskId}";
        Cache::forget($draftKey);
        $this->hasDraft = false;

        $task = PutawayTask::with('item')->find($this->executingTaskId);
        $this->splitRows = [
            [
                'locationId'   => '',
                'locationCode' => '',
                'qty'          => $task ? rtrim(rtrim(number_format((float) $task->qty, 4), '0'), '.') : '',
                'serials'      => [],
            ],
        ];
    }

    public function executeSplitTask(): void
    {
        $task = $this->executingTask;
        if (! $task) {
            return;
        }

        $serialDriven = ! empty($this->taskSerials);
        $rows = [];

        foreach ($this->splitRows as $i => $row) {
            $locationId = (int) ($row['locationId'] ?? 0);
            $serials    = array_values(array_filter((array) ($row['serials'] ?? [])));

            if ($locationId <= 0) {
                $this->dispatch('toast', message: 'Row ' . ($i + 1) . ': select a destination bin.', type: 'error');
                return;
            }

            if ($serialDriven) {
                if (empty($serials)) {
                    continue;
                }
                $qty = (string) count($serials);
            } else {
                $qty = trim((string) ($row['qty'] ?? ''));
                if ($qty === '' || (float) $qty <= 0) {
                    $this->dispatch('toast', message: 'Row ' . ($i + 1) . ': enter a valid quantity.', type: 'error');
                    return;
                }
            }

            $rows[] = [
                'location_id' => $locationId,
                'qty'         => $qty,
                'serials'     => $serials,
            ];
        }

        try {
            app(PutawayEngine::class)->executeSplits($task, $rows, auth()->user());

            // Clear draft on success
            $draftKey = "putaway_draft_{$this->getAuthId()}_{$this->executingTaskId}";
            Cache::forget($draftKey);

            unset($this->tasks);
            $this->closeExecute();
            $this->dispatch('toast', message: 'Putaway complete!', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Error: ' . $e->getMessage(), type: 'error');
        }
    }

    private function getAuthId(): int|string
    {
        return auth()->id() ?? 0;
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
