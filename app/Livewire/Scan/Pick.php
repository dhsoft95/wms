<?php

declare(strict_types=1);

namespace App\Livewire\Scan;

use App\Enums\Outbound\PickListStatus;
use App\Enums\Outbound\PickTaskStatus;
use App\Models\PickList;
use App\Models\PickTask;
use App\Services\Outbound\PickingService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Pick extends Component
{
    public string    $step          = 'scan_list';
    public string    $orderRef      = '';
    public ?PickList $pickList       = null;
    public string    $scanError     = '';

    // Task execution state
    public ?int    $activeTaskId    = null;
    public string  $pickedQty       = '';
    public string  $pickedSerial    = '';

    public function loadOrder(): void
    {
        $this->scanError = '';
        $this->pickList  = null;

        $pl = PickList::with(['deliveryOrder.consigneePartner', 'tasks.allocation.item', 'tasks.allocation.location', 'tasks.allocation.batch'])
            ->where(function ($q) {
                $q->where('number', strtoupper(trim($this->orderRef)))
                  ->orWhereHas('deliveryOrder', fn ($sq) => $sq->where('number', strtoupper(trim($this->orderRef))));
            })
            ->whereIn('status', [PickListStatus::Open->value, PickListStatus::InProgress->value])
            ->first();

        if ($pl === null) {
            $this->scanError = 'Pick list or order not found, or already completed.';
            return;
        }

        $this->pickList = $pl;
        $this->step     = 'task_list';
    }

    public function openTask(int $taskId): void
    {
        $this->activeTaskId = $taskId;
        $this->pickedQty    = '';
        $this->pickedSerial = '';
        $this->step         = 'execute_task';
    }

    public function confirmPick(): void
    {
        $task = PickTask::with(['pickList.deliveryOrder', 'allocation'])->findOrFail($this->activeTaskId);

        $qty = (float) $this->pickedQty;
        if ($qty <= 0) {
            $this->scanError = 'Enter a quantity greater than zero.';
            return;
        }

        $serials = $this->pickedSerial !== '' ? [$this->pickedSerial] : [];

        app(PickingService::class)->executeTask($task, $qty, auth()->user(), $serials);

        // Refresh pick list
        $this->pickList     = $task->pickList->fresh(['tasks.allocation.item', 'tasks.allocation.location', 'tasks.allocation.batch']);
        $this->activeTaskId = null;
        $this->pickedQty    = '';
        $this->pickedSerial = '';
        $this->step         = 'task_list';

        $allDone = $this->pickList->tasks->every(fn ($t) => $t->status !== PickTaskStatus::Open);
        if ($allDone) {
            $this->step = 'done';
        }
    }

    public function startOver(): void
    {
        $this->step         = 'scan_list';
        $this->orderRef     = '';
        $this->pickList     = null;
        $this->scanError    = '';
        $this->activeTaskId = null;
        $this->pickedQty    = '';
        $this->pickedSerial = '';
    }

    public function render(): View
    {
        return view('livewire.scan.pick')
            ->layout('components.layouts.scan', ['title' => 'Pick']);
    }
}
