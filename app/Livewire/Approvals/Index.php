<?php

declare(strict_types=1);

namespace App\Livewire\Approvals;

use App\Enums\Inbound\PurchaseOrderStatus;
use App\Models\DeliveryOrderApproval;
use App\Models\PurchaseOrder;
use App\Services\Inbound\ApprovalChainService;
use App\Services\Inbound\PurchaseOrderWorkflow;
use App\Services\Outbound\DeliveryOrderWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public string $typeFilter = 'all';

    // DO approval state
    public bool   $showDoRejectModal = false;
    public ?int   $rejectApprovalId  = null;
    public string $rejectNotes       = '';

    // PO action state
    public bool   $showPoReturnModal = false;
    public bool   $showPoRejectModal = false;
    public ?int   $actingPoId        = null;
    public string $poComment         = '';
    public string $poRejectReason    = '';

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function pendingPoApprovals(): \Illuminate\Database\Eloquent\Collection
    {
        $user    = auth()->user();
        $service = app(ApprovalChainService::class);

        return PurchaseOrder::with(['warehouse', 'department', 'requestedBy', 'lines.item.category'])
            ->where('status', PurchaseOrderStatus::PendingApproval)
            ->get()
            ->filter(fn ($po) =>
                $service->canActAtCurrentLevel($po, $user) ||
                $service->canActOnCategoryLevel($po, $user)
            )
            ->values();
    }

    #[Computed]
    public function pendingDoApprovals(): \Illuminate\Database\Eloquent\Collection
    {
        $query = DeliveryOrderApproval::with(['deliveryOrder.consigneePartner', 'deliveryOrder.requestedBy', 'deliveryOrder.virtualWarehouse'])
            ->whereNull('decision')
            ->whereHas('deliveryOrder', fn ($q) => $q->where('status', \App\Enums\Outbound\DeliveryOrderStatus::Submitted->value));

        $user = auth()->user();

        if ($user->hasRole('department-manager') || $user->hasRole('department-head')) {
            $query->where('approver_role', 'department');
        } elseif ($user->hasRole('warehouse-manager') || $user->hasRole('warehouse-supervisor')) {
            $query->where('approver_role', 'warehouse');
        }

        return $query->orderBy('created_at')->get();
    }

    #[Computed]
    public function totalPendingCount(): int
    {
        return $this->pendingPoApprovals->count() + $this->pendingDoApprovals->count();
    }

    // ─── PO approval actions ──────────────────────────────────────────────────

    public function approvePo(int $poId): void
    {
        $po = PurchaseOrder::findOrFail($poId);
        $this->authorize('approve', $po);

        try {
            app(PurchaseOrderWorkflow::class)->approveAtLevel($po, auth()->user());
            $this->dispatch('toast', message: "PO {$po->number} approved.", type: 'success');
            unset($this->pendingPoApprovals, $this->totalPendingCount);
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openPoReturnModal(int $poId): void
    {
        $this->actingPoId        = $poId;
        $this->poComment         = '';
        $this->showPoReturnModal = true;
    }

    public function closePoReturnModal(): void { $this->showPoReturnModal = false; }

    public function returnPo(): void
    {
        $this->validate(['poComment' => ['required', 'string', 'min:5']]);

        $po = PurchaseOrder::findOrFail($this->actingPoId);
        $this->authorize('approve', $po);

        try {
            app(PurchaseOrderWorkflow::class)->returnForCorrection($po, auth()->user(), $this->poComment);
            $this->showPoReturnModal = false;
            $this->dispatch('toast', message: "PO {$po->number} returned for correction.", type: 'info');
            unset($this->pendingPoApprovals, $this->totalPendingCount);
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openPoRejectModal(int $poId): void
    {
        $this->actingPoId        = $poId;
        $this->poRejectReason    = '';
        $this->showPoRejectModal = true;
    }

    public function closePoRejectModal(): void { $this->showPoRejectModal = false; }

    public function rejectPo(): void
    {
        $this->validate(['poRejectReason' => ['required', 'string', 'min:5']]);

        $po = PurchaseOrder::findOrFail($this->actingPoId);
        $this->authorize('approve', $po);

        try {
            app(PurchaseOrderWorkflow::class)->reject($po, auth()->user(), $this->poRejectReason);
            $this->showPoRejectModal = false;
            $this->dispatch('toast', message: "PO {$po->number} rejected.", type: 'info');
            unset($this->pendingPoApprovals, $this->totalPendingCount);
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    // ─── DO approval actions ──────────────────────────────────────────────────

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
