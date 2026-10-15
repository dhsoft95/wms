<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\Orders;

use App\Enums\Outbound\DeliveryOrderStatus;
use App\Models\DeliveryOrder;
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
    public function pickerUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return User::where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'picker'))
            ->orderBy('name')
            ->get();
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function submit(): void
    {
        $this->authorize('submit', $this->deliveryOrder);
        app(DeliveryOrderWorkflow::class)->submit($this->deliveryOrder, auth()->user());
        $this->dispatch('toast', message: 'Order submitted for approval.', type: 'success');
        unset($this->do);
    }

    public function allocate(): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        app(AllocationService::class)->allocate($this->deliveryOrder);
        $this->dispatch('toast', message: 'Allocation complete.', type: 'success');
        unset($this->do);
    }

    public function generatePickList(): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        $assigned = $this->assignUserId !== '' ? User::find($this->assignUserId) : null;
        app(PickingService::class)->generatePickList($this->deliveryOrder, $assigned);
        $this->showAssignModal = false;
        $this->dispatch('toast', message: 'Pick list generated.', type: 'success');
        unset($this->do);
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
        app(DeliveryOrderWorkflow::class)->reject($this->deliveryOrder, auth()->user(), $this->rejectNotes);
        $this->showRejectModal = false;
        $this->dispatch('toast', message: 'Order rejected.', type: 'success');
        unset($this->do);
    }

    public function cancel(): void
    {
        $this->authorize('cancel', $this->deliveryOrder);
        app(DeliveryOrderWorkflow::class)->cancel($this->deliveryOrder, auth()->user());
        $this->dispatch('toast', message: 'Order cancelled.', type: 'success');
        unset($this->do);
    }

    public function approveDepartment(): void
    {
        $this->authorize('approveDepartment', $this->deliveryOrder);
        app(DeliveryOrderWorkflow::class)->approve($this->deliveryOrder, auth()->user(), 'department');
        $this->dispatch('toast', message: 'Department approval recorded.', type: 'success');
        unset($this->do);
    }

    public function approveWarehouse(): void
    {
        $this->authorize('approveWarehouse', $this->deliveryOrder);
        app(DeliveryOrderWorkflow::class)->approve($this->deliveryOrder, auth()->user(), 'warehouse');
        $this->dispatch('toast', message: 'Warehouse approval recorded.', type: 'success');
        unset($this->do);
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
