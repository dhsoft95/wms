<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\PickLists;

use App\Enums\Outbound\PickListStatus;
use App\Models\Inventory\SerialNumber;
use App\Models\PickList;
use App\Models\PickTask;
use App\Models\User;
use App\Services\Outbound\PickingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $statusFilter = 'open';
    public string $search       = '';

    // Assign modal
    public bool   $showAssignModal  = false;
    public ?int   $actionPickListId = null;
    public string $assignUserId     = '';

    // Execute task modal (desktop picking)
    public bool   $showPickModal    = false;
    public ?int   $activeTaskId     = null;
    public string $pickedQty        = '';
    public array  $availableSerials = [];   // [['serial_no' => '...', 'location_code' => '...', 'at_alloc_location' => bool], ...]
    public array  $selectedSerials  = [];   // selected serial_no strings

    public function updatedSearch(): void       { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    #[Computed]
    public function pickLists(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = PickList::with(['deliveryOrder.consigneePartner', 'assignedTo', 'tasks'])
            ->withCount(['tasks', 'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'picked')]);

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search !== '') {
            $s = '%' . $this->search . '%';
            $query->where(function (Builder $q) use ($s) {
                $q->where('number', 'like', $s)
                  ->orWhereHas('deliveryOrder', fn ($sq) => $sq->where('number', 'like', $s));
            });
        }

        // VW scoping
        $user = auth()->user();
        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            if (! empty($vwIds)) {
                $query->whereHas('deliveryOrder', fn ($q) => $q->whereIn('virtual_warehouse_id', $vwIds));
            }
        }

        return $query->orderByDesc('created_at')->paginate(20);
    }

    #[Computed]
    public function statusCounts(): array
    {
        $base   = PickList::query();
        $user   = auth()->user();
        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            if (! empty($vwIds)) {
                $base->whereHas('deliveryOrder', fn ($q) => $q->whereIn('virtual_warehouse_id', $vwIds));
            }
        }
        $counts = ['all' => (clone $base)->count()];
        foreach (PickListStatus::cases() as $status) {
            $counts[$status->value] = (clone $base)->where('status', $status->value)->count();
        }
        return $counts;
    }

    #[Computed]
    public function statuses(): array
    {
        return PickListStatus::cases();
    }

    #[Computed]
    public function pickerUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return User::where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function activeTask(): ?PickTask
    {
        return $this->activeTaskId ? PickTask::with(['pickList.deliveryOrder', 'allocation.item', 'allocation.location', 'allocation.batch'])->find($this->activeTaskId) : null;
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function openAssignModal(int $pickListId): void
    {
        $this->actionPickListId = $pickListId;
        $this->assignUserId     = '';
        $this->showAssignModal  = true;
    }

    public function confirmAssign(): void
    {
        $this->validate(['assignUserId' => ['required', 'exists:users,id']]);
        $pickList = PickList::findOrFail($this->actionPickListId);
        $pickList->update(['assigned_to' => $this->assignUserId]);
        $this->showAssignModal  = false;
        $this->actionPickListId = null;
        $this->dispatch('toast', message: "Pick list {$pickList->number} assigned.", type: 'success');
        unset($this->pickLists);
    }

    public function openPickTask(int $taskId): void
    {
        $task = PickTask::with(['allocation.item', 'allocation.location', 'allocation.batch'])->find($taskId);

        $this->activeTaskId     = $taskId;
        $this->pickedQty        = $task ? number_format((float) $task->qty_to_pick, 4, '.', '') : '';
        $this->availableSerials = [];
        $this->selectedSerials  = [];

        if ($task && $task->allocation?->item?->track_serial) {
            $alloc = $task->allocation;
            $qty   = (int) round((float) $task->qty_to_pick);

            // Load all in-stock serials for this item so the picker can select from any location
            $rows = SerialNumber::with('location')
                ->where('item_id', $alloc->item_id)
                ->where('status', 'in_stock')
                ->orderByRaw('location_id = ? DESC', [$alloc->location_id])  // allocated location first
                ->orderBy('serial_no')
                ->get();

            $this->availableSerials = $rows->map(fn ($s) => [
                'serial_no'          => $s->serial_no,
                'location_code'      => $s->location?->code ?? '—',
                'at_alloc_location'  => $s->location_id === $alloc->location_id,
            ])->toArray();

            // Pre-select serials from the allocated location (up to required qty)
            $atLocation = array_values(array_filter($this->availableSerials, fn ($s) => $s['at_alloc_location']));
            $this->selectedSerials = array_column(array_slice($atLocation, 0, $qty), 'serial_no');
        }

        $this->showPickModal = true;
    }

    public function confirmPick(): void
    {
        $this->validate([
            'pickedQty' => ['required', 'numeric', 'min:0.0001'],
        ]);

        $task = PickTask::with(['pickList.deliveryOrder', 'allocation.item'])->findOrFail($this->activeTaskId);
        $by   = auth()->user();

        $serials = array_values(array_filter($this->selectedSerials, fn ($s) => $s !== ''));

        if ($task->allocation?->item?->track_serial && count($serials) === 0) {
            $this->addError('selectedSerials', 'Select at least one serial number for this item.');
            return;
        }

        if ($task->allocation?->item?->track_serial) {
            $expected = (int) round((float) $this->pickedQty);
            if (count($serials) !== $expected) {
                $this->addError('selectedSerials', "Select exactly {$expected} serial(s) for qty {$expected} — got " . count($serials) . '.');
                return;
            }
        }

        app(PickingService::class)->executeTask($task, number_format((float) $this->pickedQty, 4, '.', ''), $by, $serials);

        $this->showPickModal = false;
        $this->activeTaskId  = null;
        $this->dispatch('toast', message: 'Task picked successfully.', type: 'success');
        unset($this->pickLists);
    }

    public function unconfirmTask(int $taskId): void
    {
        $task = PickTask::with(['pickList.deliveryOrder', 'stockTransaction', 'allocation.stockBalance'])->findOrFail($taskId);
        app(PickingService::class)->unconfirmTask($task, auth()->user());
        $this->dispatch('toast', message: 'Task unconfirmed and stock returned to bin.', type: 'info');
        unset($this->pickLists);
    }

    public function render(): View
    {
        return view('livewire.outbound.pick-lists.index')
            ->layout('components.layouts.app', [
                'title'           => 'Pick Lists',
                'breadcrumbGroup' => 'Outbound',
            ]);
    }
}
