<?php

namespace App\Livewire\Inbound\Grn;

use App\Enums\GrnStatus;
use App\Enums\Inventory\SerialStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Models\Grn;
use App\Models\Inventory\SerialNumber;
use App\Models\Location;
use App\Models\PutawayTask;
use App\Services\Inbound\GrnWorkflow;
use App\Services\Inbound\PutawayEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class Show extends Component
{
    public Grn $grn;

    public string $activeTab = 'lines';

    // Variance approval inputs
    public string $approvalNotes = '';

    // Split putaway modal state
    public bool   $showExecuteModal = false;
    public ?int   $executingTaskId  = null;
    public bool   $hasDraft         = false;

    // Task details modal state
    public bool $showDetailsModal = false;
    public ?int $detailsTaskId    = null;

    /** @var array<int, array{locationId: string, locationCode: string, qty: string, serials: array<string>}> */
    public array $splitRows = [];

    /** @var array<string> Available serial numbers for the executing task */
    public array $taskSerials = [];

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
    public function executingTask(): ?PutawayTask
    {
        if ($this->executingTaskId === null) {
            return null;
        }

        return PutawayTask::with(['item', 'fromLocation', 'suggestedLocation'])->find($this->executingTaskId);
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

    /**
     * Returns matching bin locations for the bin-search dropdown.
     * #[Renderless] means this method returns data to Alpine WITHOUT re-rendering
     * the Livewire component, so Alpine's open/results state is never reset.
     *
     * @return array<int, array{id: int, code: string, barcode: string|null}>
     */
    #[Renderless]
    public function getBinSuggestions(string $search = ''): array
    {
        $query = Location::where('warehouse_id', $this->grn->warehouse_id)
            ->where('level', LocationLevel::Bin->value)
            ->where('type', LocationType::Storage->value)
            ->where('status', LocationStatus::Active->value);

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
            );
        }

        return $query->orderBy('code')
            ->limit(8)
            ->get(['id', 'code', 'barcode'])
            ->map(fn ($l) => ['id' => $l->id, 'code' => $l->code, 'barcode' => $l->barcode])
            ->toArray();
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

    // ─── Split Putaway Execute ─────────────────────────────────────────────────

    public function openExecute(int $taskId): void
    {
        $this->executingTaskId = $taskId;
        unset($this->executingTask);

        $task = PutawayTask::with('item')->find($taskId);

        // Check for saved draft
        $draftKey       = "putaway_draft_{$this->getAuthId()}_{$taskId}";
        $draft          = Cache::get($draftKey);
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

        // Load available serials for serial-tracked items
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
        unset($this->splitRowResults, $this->allocatedQty);
    }

    public function closeExecute(): void
    {
        $this->showExecuteModal = false;
        $this->executingTaskId  = null;
        $this->splitRows        = [];
        $this->taskSerials      = [];
        $this->hasDraft         = false;
        unset($this->executingTask, $this->splitRowResults, $this->allocatedQty);
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

        $task            = PutawayTask::with('item')->find($this->executingTaskId);
        $this->splitRows = [
            [
                'locationId'   => '',
                'locationCode' => '',
                'qty'          => $task ? rtrim(rtrim(number_format((float) $task->qty, 4), '0'), '.') : '',
                'serials'      => [],
            ],
        ];
    }

    private function getAuthId(): int|string
    {
        return auth()->id() ?? 0;
    }

    public function addSplitRow(): void
    {
        $this->splitRows[] = [
            'locationId'   => '',
            'locationCode' => '',
            'qty'          => '',
            'serials'      => [],
        ];
        unset($this->splitRowResults, $this->allocatedQty);
    }

    public function removeSplitRow(int $index): void
    {
        array_splice($this->splitRows, $index, 1);
        $this->splitRows = array_values($this->splitRows);
        unset($this->splitRowResults, $this->allocatedQty);
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
                // Skip rows with no serials selected — user left bin empty
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
