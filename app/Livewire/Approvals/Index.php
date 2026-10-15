<?php

declare(strict_types=1);

namespace App\Livewire\Approvals;

use App\Models\DeliveryOrderApproval;
use App\Services\Outbound\DeliveryOrderWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public string $typeFilter = 'all';    // all | do_approval | adjustment

    // DO approval state
    public bool   $showDoRejectModal = false;
    public ?int   $rejectApprovalId  = null;
    public string $rejectNotes       = '';

    #[Computed]
    public function pendingDoApprovals(): \Illuminate\Database\Eloquent\Collection
    {
        $query = DeliveryOrderApproval::with(['deliveryOrder.consigneePartner', 'deliveryOrder.requestedBy', 'deliveryOrder.virtualWarehouse'])
            ->whereNull('decision')
            ->whereHas('deliveryOrder', fn ($q) => $q->where('status', \App\Enums\Outbound\DeliveryOrderStatus::Submitted->value));

        $user = auth()->user();

        // Only show approvals relevant to this user's role
        if ($user->hasRole('department-manager') || $user->hasRole('department-head')) {
            $query->where('approver_role', 'department');
        } elseif ($user->hasRole('warehouse-manager') || $user->hasRole('warehouse-supervisor')) {
            $query->where('approver_role', 'warehouse');
        }
        // system-admin sees all via Gate::before

        return $query->orderBy('created_at')->get();
    }

    #[Computed]
    public function totalPendingCount(): int
    {
        return $this->pendingDoApprovals->count();
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function approveOrder(int $approvalId): void
    {
        $approval = DeliveryOrderApproval::with('deliveryOrder')->findOrFail($approvalId);
        $this->authorize('approveDepartment', $approval->deliveryOrder);

        app(DeliveryOrderWorkflow::class)->approve(
            $approval->deliveryOrder,
            auth()->user(),
            (int) $approval->level,
        );

        $this->dispatch('toast', message: "Order {$approval->deliveryOrder->number} approved.", type: 'success');
        unset($this->pendingDoApprovals, $this->totalPendingCount);
    }

    public function openRejectModal(int $approvalId): void
    {
        $this->rejectApprovalId  = $approvalId;
        $this->rejectNotes       = '';
        $this->showDoRejectModal = true;
    }

    public function confirmReject(): void
    {
        $this->validate(['rejectNotes' => ['required', 'string', 'min:5']]);

        $approval = DeliveryOrderApproval::with('deliveryOrder')->findOrFail($this->rejectApprovalId);
        $this->authorize('cancel', $approval->deliveryOrder);

        app(DeliveryOrderWorkflow::class)->reject(
            $approval->deliveryOrder,
            auth()->user(),
            $this->rejectNotes,
        );

        $this->showDoRejectModal = false;
        $this->rejectApprovalId  = null;
        $this->rejectNotes       = '';

        $this->dispatch('toast', message: "Order {$approval->deliveryOrder->number} rejected.", type: 'success');
        unset($this->pendingDoApprovals, $this->totalPendingCount);
    }

    public function render(): View
    {
        return view('livewire.approvals.index')
            ->layout('components.layouts.app', [
                'title'           => 'Approvals Inbox',
                'breadcrumbGroup' => 'Overview',
            ]);
    }
}
