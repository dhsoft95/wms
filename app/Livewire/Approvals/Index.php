<?php

declare(strict_types=1);

namespace App\Livewire\Approvals;

use App\Enums\Inbound\PurchaseOrderStatus;
use App\Enums\Inventory\DisposalStatus;
use App\Enums\Inventory\StockStatusRequestStatus;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\TransferStatus;
use App\Models\DeliveryOrder;
use App\Models\DisposalRequest;
use App\Models\PurchaseOrder;
use App\Models\StockStatusRequest;
use App\Models\StockTransfer;
use App\Services\Inbound\ApprovalChainService;
use App\Services\Inbound\PurchaseOrderWorkflow;
use App\Services\Inventory\DisposalWorkflow;
use App\Services\Inventory\StockStatusRequestWorkflow;
use App\Services\Inventory\TransferApprovalService;
use App\Services\Inventory\TransferWorkflow;
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
            $this->pendingStnApprovals,
            $this->totalPendingCount,
        );
    }

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
                PurchaseOrderStatus::AwaitingRequesterCorrection,
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
                // HOD sees ReturnedForCorrection POs for their dept (their turn to forward)
                if ($po->status === PurchaseOrderStatus::ReturnedForCorrection
                    && $po->department?->head_user_id === $user->id) {
                    return true;
                }
                // Requester: PendingApproval is not their action — hide from both tabs
                if ($po->status === PurchaseOrderStatus::PendingApproval
                    && $po->requested_by === $user->id
                    && $po->department?->head_user_id !== $user->id) {
                    return false;
                }
                // Requester: ReturnedForCorrection is HOD's turn — hide from requester
                if ($po->status === PurchaseOrderStatus::ReturnedForCorrection
                    && $po->requested_by === $user->id
                    && $po->department?->head_user_id !== $user->id) {
                    return false;
                }
                // Requester: AwaitingRequesterCorrection means HOD forwarded — requester can act
                if ($po->status === PurchaseOrderStatus::AwaitingRequesterCorrection
                    && $po->requested_by === $user->id) {
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
        $user  = auth()->user();

        $query = DeliveryOrder::with(['department', 'consigneePartner', 'requestedBy', 'virtualWarehouse', 'approvals' => fn ($q) => $q->latest()]);

        $canAccept = $user->can('orders.allocate');

        if ($this->tab === 'pending') {
            $query->whereIn('status', [
                DeliveryOrderStatus::Submitted,
                DeliveryOrderStatus::Approved,
                DeliveryOrderStatus::ReturnedForCorrection,
                DeliveryOrderStatus::AwaitingRequesterCorrection,
            ]);
        } else {
            $query->whereNotIn('status', [DeliveryOrderStatus::Draft]);
        }

        return $query->get()
            ->filter(function ($do) use ($user, $canAccept) {
                if ($user->hasRole('system-admin')) {
                    return true;
                }
                // WM sees Approved DOs waiting for acceptance
                if ($do->status === DeliveryOrderStatus::Approved
                    && $user->hasRole('warehouse-manager') && $canAccept) {
                    return true;
                }
                // HOD sees ReturnedForCorrection for their dept (their turn to forward)
                if ($do->status === DeliveryOrderStatus::ReturnedForCorrection
                    && $do->department?->head_user_id === $user->id) {
                    return true;
                }
                // Requester: Submitted is not their action — hide
                if ($do->status === DeliveryOrderStatus::Submitted
                    && $do->requested_by === $user->id
                    && $do->department?->head_user_id !== $user->id) {
                    return false;
                }
                // Requester: AwaitingRequesterCorrection — show to requester
                if ($do->status === DeliveryOrderStatus::AwaitingRequesterCorrection
                    && $do->requested_by === $user->id) {
                    return true;
                }
                if ($this->tab === 'all') {
                    if ($do->requested_by === $user->id) {
                        return true;
                    }
                    if ($do->approvals->contains('decided_by', $user->id)) {
                        return true;
                    }
                }
                // HOD sees Submitted DOs for their department
                return $do->status === DeliveryOrderStatus::Submitted
                    && $do->department?->head_user_id === $user->id;
            })
            ->values();
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
    public function pendingStnApprovals(): \Illuminate\Database\Eloquent\Collection
    {
        $user    = auth()->user();
        $service = app(TransferApprovalService::class);

        $query = StockTransfer::with([
            'fromVirtualWarehouse',
            'toVirtualWarehouse',
            'createdBy',
            'department',
            'approvals' => fn ($q) => $q->latest(),
        ]);

        if ($this->tab === 'pending') {
            $query->whereIn('status', [
                TransferStatus::Submitted,
                TransferStatus::ReturnedForCorrection,
                TransferStatus::AwaitingRequesterCorrection,
            ]);
        } else {
            $query->whereNotIn('status', [TransferStatus::Draft]);
        }

        return $query->get()
            ->filter(function ($stn) use ($user, $service) {
                if ($user->hasRole('system-admin')) {
                    return true;
                }
                // HOD sees Submitted STNs from their department
                if ($stn->status === TransferStatus::Submitted
                    && $stn->department?->head_user_id === $user->id) {
                    return true;
                }
                // HOD sees ReturnedForCorrection (their turn to forward to requester)
                if ($stn->status === TransferStatus::ReturnedForCorrection
                    && $stn->department?->head_user_id === $user->id) {
                    return true;
                }
                // Requester: AwaitingRequesterCorrection — they can resubmit
                if ($stn->status === TransferStatus::AwaitingRequesterCorrection
                    && $stn->created_by === $user->id) {
                    return true;
                }
                // Submitted: hide from plain requester (not an approver or HOD)
                if ($stn->status === TransferStatus::Submitted
                    && $stn->created_by === $user->id
                    && ! $service->canActAtCurrentLevel($stn, $user)) {
                    return false;
                }
                if ($this->tab === 'all') {
                    if ($stn->created_by === $user->id) {
                        return true;
                    }
                    if ($stn->approvals->contains('decided_by', $user->id)) {
                        return true;
                    }
                }
                return $service->canActAtCurrentLevel($stn, $user);
            })
            ->values();
    }

    #[Computed]
    public function totalPendingCount(): int
    {
        return $this->pendingPoApprovals->count()
            + $this->pendingDoApprovals->count()
            + $this->pendingDisposalApprovals->count()
            + $this->pendingStockStatusApprovals->count()
            + $this->pendingStnApprovals->count();
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

    // ─── STN approval actions ─────────────────────────────────────────────────

    public function approveStn(int $id): void
    {
        $stn = StockTransfer::findOrFail($id);
        $this->authorize('approveDepartment', $stn);

        try {
            app(TransferWorkflow::class)->approveDepartment($stn, auth()->user());
            $this->dispatch('toast', message: "Transfer {$stn->number} approved.", type: 'success');
            unset($this->pendingStnApprovals, $this->totalPendingCount);
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
