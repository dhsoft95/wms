<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\PickLists;

use App\Enums\Outbound\PickListStatus;
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
    public string $pickedSerial     = '';

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
        return User::where('is_active', true)
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
        $this->activeTaskId  = $taskId;
        $this->pickedQty     = '';
        $this->pickedSerial  = '';
        $this->showPickModal = true;
    }

    public function confirmPick(): void
    {
        $this->validate([
            'pickedQty' => ['required', 'numeric', 'min:0.0001'],
        ]);

        $task    = PickTask::with(['pickList.deliveryOrder', 'allocation'])->findOrFail($this->activeTaskId);
        $by      = auth()->user();
        $serials = $this->pickedSerial !== '' ? [$this->pickedSerial] : [];

        app(PickingService::class)->executeTask($task, (float) $this->pickedQty, $by, $serials);

        $this->showPickModal = false;
        $this->activeTaskId  = null;
        $this->dispatch('toast', message: 'Task picked successfully.', type: 'success');
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
