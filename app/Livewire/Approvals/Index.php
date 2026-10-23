<?php

declare(strict_types=1);

namespace App\Livewire\Approvals;

use App\Enums\Inbound\PurchaseOrderStatus;
use App\Enums\Inventory\DisposalStatus;
use App\Enums\Inventory\StockStatusRequestStatus;
use App\Models\DeliveryOrderApproval;
use App\Models\DisposalRequest;
use App\Models\PurchaseOrder;
use App\Models\StockStatusRequest;
use App\Services\Inbound\ApprovalChainService;
use App\Services\Inbound\PurchaseOrderWorkflow;
use App\Services\Inventory\DisposalWorkflow;
use App\Services\Inventory\StockStatusRequestWorkflow;
use App\Services\Outbound\DeliveryOrderWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public string $tab = 'pending';

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        unset(
            $this->pendingPoApprovals,
            $this->pendingDoApprovals,
            $this->pendingDisposalApprovals,
            $this->pendingStockStatusApprovals,
            $this->totalPendingCount,
        );
    }

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

        $query = PurchaseOrder::with(['warehouse', 'department', 'requestedBy', 'lines.item.category', 'approvals' => fn ($q) => $q->latest()]);

        if ($this->tab === 'pending') {
            $query->whereIn('status', [
                PurchaseOrderStatus::PendingApproval,
                PurchaseOrderStatus::ReturnedForCorrection,
                PurchaseOrderStatus::ApprovedPendingAcceptance,
            ]);
        } else {
            $query->whereNotIn('status', [PurchaseOrderStatus::Draft]);
        }

        $canAccept = $user->can('po.accept');

        return $query->get()
            ->filter(function ($po) use ($user, $service, $canAccept) {
                if ($user->hasRole('system-admin')) {
                    return true;
                }
                // WM: sees POs awaiting acceptance
                if ($user->hasRole('warehouse-manager') && $canAccept
                    && $po->status === PurchaseOrderStatus::ApprovedPendingAcceptance) {
                    return true;
                }
                // Returned POs: visible to the requester AND the dept head
                if ($po->status === PurchaseOrderStatus::ReturnedForCorrection
                    && ($po->requested_by === $user->id
                        || $po->department?->head_user_id === $user->id)) {
                    return true;
                }
                if ($this->tab === 'all') {
                    if ($po->requested_by === $user->id) {
                        return true;
                    }
                    if ($po->approvals->contains('user_id', $user->id)) {
                        return true;
                    }
                }
                return $service->canActAtCurrentLevel($po, $user)
                    || $service->canActOnCategoryLevel($po, $user)
                    || $service->canActAsFallbackApprover($po, $user);
            })
            ->values();
    }

    #[Computed]
    public function pendingDoApprovals(): \Illuminate\Database\Eloquent\Collection
    {
        $query = DeliveryOrderApproval::with(['deliveryOrder.consigneePartner', 'deliveryOrder.requestedBy', 'deliveryOrder.virtualWarehouse']);

        if ($this->tab === 'pending') {
            $query->whereNull('decision')
                ->whereHas('deliveryOrder', fn ($q) => $q->where('status', \App\Enums\Outbound\DeliveryOrderStatus::Submitted->value));
        }

        $user = auth()->user();

        if ($user->hasRole('department-manager') || $user->hasRole('department-head')) {
            $query->where('approver_role', 'department');
        } elseif ($user->hasRole('warehouse-manager') || $user->hasRole('warehouse-supervisor')) {
            $query->where('approver_role', 'warehouse');
        }

        return $query->orderBy('created_at')->get();
    }

    #[Computed]
    public function pendingDisposalApprovals(): \Illuminate\Database\Eloquent\Collection
    {
        $user     = auth()->user();
        $workflow = app(DisposalWorkflow::class);

        $query = DisposalRequest::with(['virtualWarehouse', 'createdBy', 'department'])
            ->withCount('lines')
            ->whereNotIn('status', [DisposalStatus::Completed->value, DisposalStatus::Rejected->value]);

        if ($this->tab === 'pending') {
            $query->whereIn('status', [
                DisposalStatus::Submitted->value,
                DisposalStatus::PendingWarehouseManagerApproval->value,
            ]);
        }

        return $query->get()
            ->filter(fn ($req) => $workflow->canApprove($req, $user))
            ->values();
    }

    #[Computed]
    public function pendingStockStatusApprovals(): \Illuminate\Database\Eloquent\Collection
    {
        $user     = auth()->user();
        $workflow = app(StockStatusRequestWorkflow::class);

        $query = StockStatusRequest::with(['virtualWarehouse', 'createdBy', 'department'])
            ->withCount('lines')
            ->whereNotIn('status', [StockStatusRequestStatus::Approved->value, StockStatusRequestStatus::Rejected->value]);

        if ($this->tab === 'pending') {
            $query->whereIn('status', [
                StockStatusRequestStatus::Submitted->value,
                StockStatusRequestStatus::PendingWarehouseManagerApproval->value,
            ]);
        }

        return $query->get()
            ->filter(fn ($req) => $workflow->canApprove($req, $user))
            ->values();
    }

    #[Computed]
    public function totalPendingCount(): int
    {
        return $this->pendingPoApprovals->count()
            + $this->pendingDoApprovals->count()
            + $this->pendingDisposalApprovals->count()
            + $this->pendingStockStatusApprovals->count();
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

    // ─── Disposal approval actions ────────────────────────────────────────────

    public function approveDisposal(int $id): void
    {
        $req = DisposalRequest::findOrFail($id);
        $this->authorize('approve', $req);

        try {
            app(DisposalWorkflow::class)->approve($req, auth()->user());
            $this->dispatch('toast', message: "Disposal {$req->number} approved.", type: 'success');
            unset($this->pendingDisposalApprovals, $this->totalPendingCount);
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    // ─── SSR approval actions ─────────────────────────────────────────────────

    public function approveSsr(int $id): void
    {
        $req = StockStatusRequest::findOrFail($id);
        $this->authorize('approve', $req);

        try {
            app(StockStatusRequestWorkflow::class)->approve($req, auth()->user());
            $this->dispatch('toast', message: "SSR {$req->number} approved.", type: 'success');
            unset($this->pendingStockStatusApprovals, $this->totalPendingCount);
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
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
