<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\Orders;

use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\PickListStatus;
use App\Models\DeliveryOrder;
use App\Models\PickList;
use App\Services\Outbound\AllocationService;
use App\Services\Outbound\DeliveryOrderWorkflow;
use App\Services\Outbound\PickingService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public DeliveryOrder $deliveryOrder;  // route model binding

    public string $activeTab = 'lines';

    // Assign picker modal
    public bool   $showAssignModal = false;
    public string $assignUserId    = '';

    public function mount(DeliveryOrder $deliveryOrder): void
    {
        $this->authorize('view', $deliveryOrder);
    }

    #[Computed]
    public function do(): DeliveryOrder
    {
        return $this->deliveryOrder->load([
            'virtualWarehouse', 'warehouse', 'department',
            'consigneePartner', 'requestedBy',
            'lines.item.baseUom', 'lines.uom', 'lines.allocations.location', 'lines.allocations.batch',
            'approvals.decidedBy',
            'pickLists.assignedTo', 'pickLists.tasks',
            'dispatches.gatePass', 'dispatches.dispatchedBy',
            'proofOfDelivery',
        ]);
    }

    #[Computed]
    public function stepperStatuses(): array
    {
        return [
            DeliveryOrderStatus::Draft,
            DeliveryOrderStatus::Approved,
            DeliveryOrderStatus::Acceptance,
            DeliveryOrderStatus::Allocated,
            DeliveryOrderStatus::Picking,
            DeliveryOrderStatus::Dispatched,
        ];
    }

    public function stepperCurrentIndex(): int
    {
        // Index = the step currently highlighted (amber).
        // Steps before it are green (done). Steps after are grey (future).
        // Stepper: 0=Draft, 1=Approved, 2=Acceptance, 3=Allocated, 4=Picking, 5=Dispatched
        return match($this->deliveryOrder->status) {
            DeliveryOrderStatus::Draft,
            DeliveryOrderStatus::Rejected,
            DeliveryOrderStatus::Cancelled          => 0,  // at Draft
            DeliveryOrderStatus::Submitted          => 1,  // Draft done → at Approved (pending)
            DeliveryOrderStatus::Approved           => 2,  // Draft+Approved done → at Acceptance
            DeliveryOrderStatus::Acceptance,
            DeliveryOrderStatus::Assigned           => 3,  // → at Allocated
            DeliveryOrderStatus::Allocated,
            DeliveryOrderStatus::PartiallyAllocated => 4,  // → at Picking
            DeliveryOrderStatus::Picking,
            DeliveryOrderStatus::Picked             => 5,  // → at Dispatched
            DeliveryOrderStatus::Dispatched,
            DeliveryOrderStatus::Delivered          => 6,  // all done (6 = out of range → all green)
        };
    }

    #[Computed]
    public function activePickList(): ?PickList
    {
        return $this->deliveryOrder->pickLists()
            ->whereIn('status', [PickListStatus::Open->value, PickListStatus::InProgress->value])
            ->first();
    }

    #[Computed]
    public function pickerUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return User::where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->where('name', 'picker'))
            ->orderBy('name')
            ->get();
    }

    // Return-for-correction modal
    public bool   $showReturnModal  = false;
    public string $returnNotes      = '';

    // Reject modal
    public bool   $showRejectModal  = false;
    public string $rejectNotes      = '';

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function submit(): void
    {
        $this->authorize('submit', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->submit($this->deliveryOrder, auth()->user());
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order submitted for approval.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function approve(): void
    {
        $this->authorize('approveDepartment', $this->deliveryOrder);
        try {
            // Find the current pending level and approve it
            $pending = $this->deliveryOrder->approvals()
                ->whereNull('decision')
                ->orderBy('level')
                ->first();
            $level = $pending?->level ?? 1;
            app(DeliveryOrderWorkflow::class)->approve($this->deliveryOrder, auth()->user(), $level);
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order approved.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function returnForCorrection(): void
    {
        $this->validate(['returnNotes' => ['required', 'string', 'min:5']]);
        $this->authorize('returnOrder', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->returnForCorrection($this->deliveryOrder, auth()->user(), $this->returnNotes);
            $this->showReturnModal = false;
            $this->returnNotes     = '';
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order returned for correction.', type: 'info');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function forwardToRequester(): void
    {
        $this->authorize('forwardReturn', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->forwardToRequester($this->deliveryOrder, auth()->user());
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order sent back to requester.', type: 'info');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function rejectOrder(): void
    {
        $this->validate(['rejectNotes' => ['required', 'string', 'min:5']]);
        $this->authorize('approveDepartment', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->reject($this->deliveryOrder, auth()->user(), $this->rejectNotes);
            $this->showRejectModal = false;
            $this->rejectNotes     = '';
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order rejected.', type: 'warning');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function accept(): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->accept($this->deliveryOrder, auth()->user());
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order accepted for processing.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function assignTeam(): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->assignTeam($this->deliveryOrder, auth()->user());
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order assigned to warehouse team.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function allocate(): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        try {
            app(AllocationService::class)->allocate($this->deliveryOrder);
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Allocation complete.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function generatePickList(): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        try {
            $assigned = $this->assignUserId !== '' ? User::find($this->assignUserId) : null;
            app(PickingService::class)->generatePickList($this->deliveryOrder, $assigned);
            $this->showAssignModal = false;
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Pick list generated.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function cancel(): void
    {
        $this->authorize('cancel', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->cancel($this->deliveryOrder, auth()->user());
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order cancelled.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function cancelPickList(int $pickListId): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        try {
            $pickList = PickList::findOrFail($pickListId);
            app(PickingService::class)->cancelPickList($pickList);
            $this->deliveryOrder->refresh();
            unset($this->do, $this->activePickList);
            $this->dispatch('toast', message: 'Pick list cancelled. You may regenerate.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        return view('livewire.outbound.orders.show')
            ->layout('components.layouts.app', [
                'title'           => $this->deliveryOrder->number,
                'breadcrumbGroup' => 'Outbound',
            ]);
    }
}
