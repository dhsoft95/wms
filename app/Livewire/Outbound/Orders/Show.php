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

    // Reject modal
    public bool   $showRejectModal = false;
    public string $rejectNotes     = '';

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
            DeliveryOrderStatus::Submitted,
            DeliveryOrderStatus::Approved,
            DeliveryOrderStatus::Allocated,
            DeliveryOrderStatus::Picking,
            DeliveryOrderStatus::Picked,
            DeliveryOrderStatus::Dispatched,
            DeliveryOrderStatus::Delivered,
        ];
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

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function submit(): void
    {
        $this->authorize('submit', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->submit($this->deliveryOrder, auth()->user());
            $this->dispatch('toast', message: 'Order submitted for approval.', type: 'success');
            unset($this->do);
        } catch (\DomainException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function allocate(): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        try {
            app(AllocationService::class)->allocate($this->deliveryOrder);
            $this->dispatch('toast', message: 'Allocation complete.', type: 'success');
            unset($this->do);
        } catch (\DomainException $e) {
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
            $this->dispatch('toast', message: 'Pick list generated.', type: 'success');
            unset($this->do);
        } catch (\DomainException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openRejectModal(): void
    {
        $this->rejectNotes    = '';
        $this->showRejectModal = true;
    }

    public function confirmReject(): void
    {
        $this->validate(['rejectNotes' => ['required', 'string', 'min:5']]);
        $this->authorize('cancel', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->reject($this->deliveryOrder, auth()->user(), $this->rejectNotes);
            $this->showRejectModal = false;
            $this->dispatch('toast', message: 'Order rejected.', type: 'success');
            unset($this->do);
        } catch (\DomainException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function cancel(): void
    {
        $this->authorize('cancel', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->cancel($this->deliveryOrder, auth()->user());
            $this->dispatch('toast', message: 'Order cancelled.', type: 'success');
            unset($this->do);
        } catch (\DomainException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function approveDepartment(): void
    {
        $this->authorize('approveDepartment', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->approve($this->deliveryOrder, auth()->user(), 'department_head');
            $this->dispatch('toast', message: 'Department approval recorded.', type: 'success');
            unset($this->do);
        } catch (\DomainException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function approveWarehouse(): void
    {
        $this->authorize('approveWarehouse', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->approve($this->deliveryOrder, auth()->user(), 'warehouse_manager');
            $this->dispatch('toast', message: 'Warehouse approval recorded.', type: 'success');
            unset($this->do);
        } catch (\DomainException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function cancelPickList(int $pickListId): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        try {
            $pickList = PickList::findOrFail($pickListId);
            app(PickingService::class)->cancelPickList($pickList);
            $this->dispatch('toast', message: 'Pick list cancelled. You may regenerate.', type: 'success');
            unset($this->do);
        } catch (\DomainException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
        unset($this->activePickList);
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
