<?php

declare(strict_types=1);

namespace App\Livewire\Inbound\Orders;

use App\Enums\Inbound\PurchaseOrderStatus;
use App\Models\Location;
use App\Models\PoIssue;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Foundation\SettingsService;
use App\Services\Inbound\ApprovalChainService;
use App\Services\Inbound\GrnWorkflow;
use App\Services\Inbound\PurchaseOrderWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public PurchaseOrder $purchaseOrder;

    // ─── Modal flags ──────────────────────────────────────────────────────────
    public bool $showReturnModal      = false;
    public bool $showRejectModal      = false;
    public bool $showWmRejectModal    = false;
    public bool $showForwardModal     = false;
    public bool $showAssignModal      = false;
    public bool $showReceiveModal     = false;
    public bool $showSendBackModal    = false;
    public bool $showIssueModal       = false;
    public bool $showResolveModal     = false;
    public bool $showCloseShortModal  = false;

    // ─── Modal inputs ─────────────────────────────────────────────────────────
    public string $returnComment        = '';
    public string $rejectReason         = '';
    public string $wmRejectReason       = '';
    public string $forwardComment       = '';
    public string $assignType           = 'supervisor';
    public string $assignUserId         = '';
    public string $sendBackComment      = '';
    public string $issueDescription     = '';
    public ?int   $resolveIssueId       = null;
    public string $resolveNotes         = '';
    public string $closeShortReason     = '';

    // ─── Start receiving inputs ───────────────────────────────────────────────
    public string $receiveDockId             = '';
    public string $receiveVehicleType        = '';
    public string $receiveVehiclePlate       = '';
    public string $receiveDriverName         = '';
    public string $receiveDriverPhone        = '';
    public string $receiveDriverLicence      = '';
    public string $receiveTransporter        = '';
    public string $receiveDeliveryType       = 'full';
    // Container-specific fields (shown when PO package_type = container)
    public string $receiveContainerNo       = '';
    public string $receiveContainerSizeType = '';
    public string $receiveContainerSealNo          = '';
    public string $receiveContainerShippingLineId  = '';

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        $this->purchaseOrder = $this->loadPo($purchaseOrder);
    }

    private function loadPo(PurchaseOrder $po): PurchaseOrder
    {
        return $po->load([
            'lines.item',
            'lines.uom',
            'warehouse',
            'virtualWarehouse',
            'supplierPartner',
            'consigneePartner',
            'requestedBy',
            'department',
            'assignedSupervisor',
            'assignedClerk',
            'approvals.user',
            'grns.lines',
            'issues.raisedBy',
        ]);
    }

    private function reload(): void
    {
        $this->loadPo($this->purchaseOrder->refresh());
        unset($this->receivingDocks, $this->supervisors, $this->clerks, $this->vehicleTypes, $this->canActOnApprovals, $this->canForwardReturn);
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function receivingDocks(): \Illuminate\Database\Eloquent\Collection
    {
        return Location::where('warehouse_id', $this->purchaseOrder->warehouse_id)
            ->where('type', 'receiving_dock')
            ->where('status', 'active')
            ->get(['id', 'code']);
    }

    #[Computed]
    public function supervisors(): \Illuminate\Database\Eloquent\Collection
    {
        return User::role('warehouse-supervisor')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    #[Computed]
    public function clerks(): \Illuminate\Database\Eloquent\Collection
    {
        return User::role('storekeeper')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    #[Computed]
    public function vehicleTypes(): array
    {
        $raw = SettingsService::get('vehicle_types', 'Truck,Van,Pickup,Trailer,Other');
        return array_filter(array_map('trim', explode(',', $raw)));
    }

    #[Computed]
    public function shippingLines(): \Illuminate\Database\Eloquent\Collection
    {
        return \App\Models\Partner::where('is_active', true)
            ->where('type', 'like', '%"shipping_line"%')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function canForwardReturn(): bool
    {
        if ($this->purchaseOrder->status !== PurchaseOrderStatus::ReturnedForCorrection) {
            return false;
        }
        $user = auth()->user();
        return $user->hasRole('system-admin')
            || $this->purchaseOrder->department?->head_user_id === $user->id;
    }

    #[Computed]
    public function canActOnApprovals(): bool
    {
        $user = auth()->user();

        if ($user->hasRole('system-admin')) {
            return true;
        }

        $svc = app(ApprovalChainService::class);
        return $svc->canActAtCurrentLevel($this->purchaseOrder, $user)
            || $svc->canActOnCategoryLevel($this->purchaseOrder, $user)
            || $svc->canActAsFallbackApprover($this->purchaseOrder, $user);
    }

    #[Computed]
    public function timelineSteps(): array
    {
        return [
            ['status' => PurchaseOrderStatus::Draft,                     'label' => 'Draft'],
            ['status' => PurchaseOrderStatus::PendingApproval,           'label' => 'Approval'],
            ['status' => PurchaseOrderStatus::ApprovedPendingAcceptance, 'label' => 'Acceptance'],
            ['status' => PurchaseOrderStatus::AcceptedPendingAssignment, 'label' => 'Assignment'],
            ['status' => PurchaseOrderStatus::AssignedPendingReceiving,  'label' => 'Receiving'],
            ['status' => PurchaseOrderStatus::VerifiedClosed,            'label' => 'Closed'],
        ];
    }

    /**
     * Map every internal status onto the 6-step visible timeline.
     * Putaway, validation and verification are internal warehouse processes
     * — all fold back into the Receiving step until the PO is closed.
     */
    public function timelineStatus(): PurchaseOrderStatus
    {
        return match ($this->purchaseOrder->status) {
            // All receiving/putaway sub-states fold into the Receiving step
            PurchaseOrderStatus::ReceivingInProgress,
            PurchaseOrderStatus::PartiallyReceived,
            PurchaseOrderStatus::PendingPutaway,
            PurchaseOrderStatus::PutawayInProgress,
            PurchaseOrderStatus::PendingSupervisorValidation,
            PurchaseOrderStatus::CompletedPendingVerification,
            PurchaseOrderStatus::IssueRaised,
            PurchaseOrderStatus::IssueResolved => PurchaseOrderStatus::AssignedPendingReceiving,
            default                            => $this->purchaseOrder->status,
        };
    }

    // ─── Submit ───────────────────────────────────────────────────────────────

    public function submit(): void
    {
        $this->authorize('submit', $this->purchaseOrder);
        try {
            app(PurchaseOrderWorkflow::class)->submit($this->purchaseOrder, auth()->user());
            $this->reload();
            $this->dispatch('toast', message: 'Submitted for approval.', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    // ─── Approval ─────────────────────────────────────────────────────────────

    public function approve(): void
    {
        $this->authorize('approve', $this->purchaseOrder);
        try {
            app(PurchaseOrderWorkflow::class)->approveAtLevel($this->purchaseOrder, auth()->user());
            $this->reload();
            $this->dispatch('toast', message: 'Approved.', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openReturnModal(): void  { $this->returnComment = ''; $this->showReturnModal = true; }
    public function closeReturnModal(): void { $this->showReturnModal = false; }

    public function returnToPreviousLevel(): void
    {
        $this->validate(['returnComment' => ['required', 'string', 'min:5']]);
        $this->authorize('approve', $this->purchaseOrder);
        try {
            app(PurchaseOrderWorkflow::class)->returnToPreviousLevel($this->purchaseOrder, auth()->user(), $this->returnComment);
            $this->reload();
            $this->showReturnModal = false;
            $this->dispatch('toast', message: 'Sent back with remark.', type: 'info');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openRejectModal(): void  { $this->rejectReason = ''; $this->showRejectModal = true; }
    public function closeRejectModal(): void { $this->showRejectModal = false; }

    public function rejectApproval(): void
    {
        $this->validate(['rejectReason' => ['required', 'string', 'min:5']]);
        $this->authorize('approve', $this->purchaseOrder);
        try {
            app(PurchaseOrderWorkflow::class)->reject($this->purchaseOrder, auth()->user(), $this->rejectReason);
            $this->reload();
            $this->showRejectModal = false;
            $this->dispatch('toast', message: 'Purchase order rejected.', type: 'info');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    // ─── Warehouse manager accept ─────────────────────────────────────────────

    public function accept(): void
    {
        $this->authorize('accept', $this->purchaseOrder);
        try {
            app(PurchaseOrderWorkflow::class)->accept($this->purchaseOrder, auth()->user());
            $this->reload();
            $this->dispatch('toast', message: 'Purchase order accepted.', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openWmRejectModal(): void  { $this->wmRejectReason = ''; $this->showWmRejectModal = true; }
    public function closeWmRejectModal(): void { $this->showWmRejectModal = false; }

    public function rejectByWarehouse(): void
    {
        $this->validate(['wmRejectReason' => ['required', 'string', 'min:5']]);
        $this->authorize('accept', $this->purchaseOrder);
        try {
            app(PurchaseOrderWorkflow::class)->rejectByWarehouse($this->purchaseOrder, auth()->user(), $this->wmRejectReason);
            $this->reload();
            $this->showWmRejectModal = false;
            $this->dispatch('toast', message: 'Sent back to department.', type: 'info');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openForwardModal(): void  { $this->forwardComment = ''; $this->showForwardModal = true; }
    public function closeForwardModal(): void { $this->showForwardModal = false; }

    public function forwardToRequester(): void
    {
        $this->validate(['forwardComment' => ['required', 'string', 'min:5']]);
        try {
            app(PurchaseOrderWorkflow::class)->forwardReturnToRequester($this->purchaseOrder, auth()->user(), $this->forwardComment);
            $this->reload();
            $this->showForwardModal = false;
            $this->dispatch('toast', message: 'Sent back to requester with your note.', type: 'info');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    // ─── Assignment ───────────────────────────────────────────────────────────

    public function openAssignSupervisor(): void
    {
        $this->assignType    = 'supervisor';
        $this->assignUserId  = '';
        $this->showAssignModal = true;
        unset($this->supervisors);
    }

    public function openAssignClerk(): void
    {
        $this->assignType    = 'clerk';
        $this->assignUserId  = '';
        $this->showAssignModal = true;
        unset($this->clerks);
    }

    public function closeAssignModal(): void { $this->showAssignModal = false; }

    public function saveAssignment(): void
    {
        $this->validate(['assignUserId' => ['required', 'exists:users,id']]);
        $this->authorize('assign', $this->purchaseOrder);

        try {
            $user = User::findOrFail((int) $this->assignUserId);
            $wf   = app(PurchaseOrderWorkflow::class);

            if ($this->assignType === 'supervisor') {
                $wf->assignSupervisor($this->purchaseOrder, $user, auth()->user());
                $msg = "Supervisor {$user->name} assigned.";
            } else {
                $wf->assignClerk($this->purchaseOrder, $user, auth()->user());
                $msg = "Clerk {$user->name} assigned.";
            }

            $this->reload();
            $this->showAssignModal = false;
            $this->dispatch('toast', message: $msg, type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    // ─── Start receiving ──────────────────────────────────────────────────────

    public function openReceiveModal(): void
    {
        $this->receiveDockId             = '';
        $this->receiveVehicleType        = '';
        $this->receiveVehiclePlate       = '';
        $this->receiveDriverName         = '';
        $this->receiveDriverPhone        = '';
        $this->receiveDriverLicence      = '';
        $this->receiveTransporter        = '';
        $this->receiveDeliveryType       = 'full';

        $this->receiveContainerNo        = '';
        $this->receiveContainerSizeType  = '';
        $this->receiveContainerSealNo         = '';
        $this->receiveContainerShippingLineId = '';
        $this->showReceiveModal          = true;
        unset($this->receivingDocks, $this->vehicleTypes);
    }

    public function closeReceiveModal(): void { $this->showReceiveModal = false; }

    public function startReceiving(): void
    {
        $isContainer = $this->purchaseOrder->package_type?->value === 'container';

        $rules = ['receiveDockId' => ['required', 'exists:locations,id']];
        if ($isContainer) {
            $rules['receiveContainerNo']       = ['required', 'string', 'max:20'];
            $rules['receiveContainerSizeType'] = ['required', 'string'];
            $rules['receiveContainerSealNo']   = ['required', 'string', 'max:50'];
        }
        $this->validate($rules);
        $this->authorize('receive', $this->purchaseOrder);

        try {
            $vehicleData = [
                'vehicle_type'          => $this->receiveVehicleType ?: null,
                'vehicle_plate'         => $this->receiveVehiclePlate ?: null,
                'driver_name'           => $this->receiveDriverName ?: null,
                'driver_phone'          => $this->receiveDriverPhone ?: null,
                'driver_licence'        => $this->receiveDriverLicence ?: null,
                'transporter_name'      => $this->receiveTransporter ?: null,
                'delivery_type'         => $this->receiveDeliveryType,
                'seal_check_result'     => null,
                'seal_mismatch_reason'  => null,
                'container_no'          => $isContainer ? strtoupper($this->receiveContainerNo) : null,
                'container_size_type'   => $isContainer ? $this->receiveContainerSizeType : null,
                'container_seal_no'            => $isContainer ? ($this->receiveContainerSealNo ?: null) : null,
                'container_shipping_line_id'   => $isContainer ? ($this->receiveContainerShippingLineId ?: null) : null,
            ];

            $grn = app(GrnWorkflow::class)->createDraftForPO(
                $this->purchaseOrder->fresh(),
                (int) $this->receiveDockId,
                $vehicleData,
                auth()->user(),
            );

            $this->dispatch('toast', message: 'Receiving tally created.', type: 'success');
            $this->redirect(route('inbound.grn.tally', $grn), navigate: true);
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    // ─── Supervisor validation ────────────────────────────────────────────────

    public function validateReceiving(): void
    {
        $this->authorize('validate', $this->purchaseOrder);
        try {
            app(PurchaseOrderWorkflow::class)->validateBySupevisor($this->purchaseOrder, auth()->user());
            $this->reload();
            $this->dispatch('toast', message: 'Receiving validated.', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openSendBackModal(): void  { $this->sendBackComment = ''; $this->showSendBackModal = true; }
    public function closeSendBackModal(): void { $this->showSendBackModal = false; }

    public function sendBackToClerk(): void
    {
        $this->validate(['sendBackComment' => ['required', 'string', 'min:5']]);
        $this->authorize('validate', $this->purchaseOrder);
        try {
            app(PurchaseOrderWorkflow::class)->sendBackFromValidation($this->purchaseOrder, auth()->user(), $this->sendBackComment);
            $this->reload();
            $this->showSendBackModal = false;
            $this->dispatch('toast', message: 'Sent back to clerk.', type: 'info');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    // ─── Verify & close / issue ───────────────────────────────────────────────

    public function verifyAndClose(): void
    {
        $this->authorize('verify', $this->purchaseOrder);
        try {
            app(PurchaseOrderWorkflow::class)->verifyAndClose($this->purchaseOrder, auth()->user());
            $this->reload();
            $this->dispatch('toast', message: 'Purchase order verified and closed.', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openIssueModal(): void  { $this->issueDescription = ''; $this->showIssueModal = true; }
    public function closeIssueModal(): void { $this->showIssueModal = false; }

    public function raiseIssue(): void
    {
        $this->validate(['issueDescription' => ['required', 'string', 'min:10']]);
        $this->authorize('verify', $this->purchaseOrder);
        try {
            app(PurchaseOrderWorkflow::class)->raiseIssue($this->purchaseOrder, auth()->user(), $this->issueDescription);
            $this->reload();
            $this->showIssueModal = false;
            $this->dispatch('toast', message: 'Issue raised. Warehouse manager notified.', type: 'info');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openResolveModal(int $issueId): void
    {
        $this->resolveIssueId = $issueId;
        $this->resolveNotes   = '';
        $this->showResolveModal = true;
    }

    public function closeResolveModal(): void { $this->showResolveModal = false; }

    public function resolveIssue(): void
    {
        $this->validate(['resolveNotes' => ['required', 'string', 'min:10']]);
        $this->authorize('resolveIssue', $this->purchaseOrder);

        try {
            $issue = PoIssue::findOrFail($this->resolveIssueId);
            app(PurchaseOrderWorkflow::class)->resolveIssue($issue, auth()->user(), $this->resolveNotes, null);
            $this->reload();
            $this->showResolveModal = false;
            $this->dispatch('toast', message: 'Issue resolved. Approvers notified.', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    // ─── Cancel / close short ─────────────────────────────────────────────────

    public function closeOrder(): void
    {
        try {
            app(PurchaseOrderWorkflow::class)->closeOrder($this->purchaseOrder, auth()->user());
            $this->reload();
            $this->dispatch('toast', message: 'Purchase order closed.', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function cancel(): void
    {
        $this->authorize('cancel', $this->purchaseOrder);
        try {
            app(PurchaseOrderWorkflow::class)->cancel($this->purchaseOrder, 'Cancelled by user.', auth()->user());
            $this->reload();
            $this->dispatch('toast', message: 'Purchase order cancelled.', type: 'info');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openCloseShortModal(): void  { $this->closeShortReason = ''; $this->showCloseShortModal = true; }
    public function closeCloseShortModal(): void { $this->showCloseShortModal = false; }

    public function closeShort(): void
    {
        $this->validate(['closeShortReason' => ['required', 'string', 'min:5']]);
        $this->authorize('closeShort', $this->purchaseOrder);
        try {
            app(PurchaseOrderWorkflow::class)->closeShort($this->purchaseOrder, auth()->user(), $this->closeShortReason);
            $this->reload();
            $this->showCloseShortModal = false;
            $this->dispatch('toast', message: 'Short delivery closed.', type: 'info');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        return view('livewire.inbound.orders.show')
            ->layout('components.layouts.app', [
                'title'           => "PO {$this->purchaseOrder->number}",
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
