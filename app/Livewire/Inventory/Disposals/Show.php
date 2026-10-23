<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\Disposals;

use App\Enums\Inventory\DisposalMethod;
use App\Enums\Inventory\DisposalStatus;
use App\Models\DisposalRequest;
use App\Models\Location;
use App\Services\Inventory\DisposalWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public DisposalRequest $disposalRequest;

    // Move to disposal area
    public bool $showMoveModal       = false;
    public int  $disposalLocationId  = 0;

    // Approve/Reject
    public bool   $showApproveModal   = false;
    public bool   $showRejectModal    = false;
    public string $approveComment     = '';
    public string $rejectReason       = '';

    // Complete
    public bool   $showCompleteModal  = false;
    public array  $completionData     = [];

    public function mount(DisposalRequest $disposalRequest): void
    {
        $this->disposalRequest = $disposalRequest;
        $this->completionData  = [
            'buyer_name'           => '',
            'sale_price'           => '',
            'sale_date'            => '',
            'receipt_file_path'    => '',
            'destroy_method'       => '',
            'destroy_date'         => '',
            'witness_name'         => '',
            'certificate_file_path' => '',
        ];
    }

    #[Computed]
    public function disposalLocations()
    {
        return Location::active()->bin()->where('type', 'disposal')->orderBy('code')->get();
    }

    // ─── Approve ─────────────────────────────────────────────────────────────

    public function openApproveModal(): void { $this->showApproveModal = true; }
    public function closeApproveModal(): void { $this->showApproveModal = false; }

    public function approve(DisposalWorkflow $workflow): void
    {
        $this->authorize('approve', $this->disposalRequest);

        try {
            $workflow->approve($this->disposalRequest, auth()->user(), $this->approveComment ?: null);
            $this->disposalRequest->refresh();
            $this->showApproveModal = false;
            $this->dispatch('toast', message: 'Disposal request approved.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    // ─── Reject ───────────────────────────────────────────────────────────────

    public function openRejectModal(): void { $this->showRejectModal = true; }
    public function closeRejectModal(): void { $this->showRejectModal = false; }

    public function reject(DisposalWorkflow $workflow): void
    {
        $this->validate(['rejectReason' => ['required', 'string', 'min:5']]);
        $this->authorize('reject', $this->disposalRequest);

        try {
            $workflow->reject($this->disposalRequest, auth()->user(), $this->rejectReason);
            $this->disposalRequest->refresh();
            $this->showRejectModal = false;
            $this->dispatch('toast', message: 'Disposal request rejected.', type: 'info');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    // ─── Move to disposal area ─────────────────────────────────────────────────

    public function openMoveModal(): void { $this->showMoveModal = true; }
    public function closeMoveModal(): void { $this->showMoveModal = false; }

    public function moveToDisposalArea(DisposalWorkflow $workflow): void
    {
        $this->validate(['disposalLocationId' => ['required', 'integer', 'exists:locations,id']]);
        $this->authorize('execute', $this->disposalRequest);

        try {
            $workflow->moveToDisposalArea($this->disposalRequest, $this->disposalLocationId, auth()->user());
            $this->disposalRequest->refresh();
            $this->showMoveModal = false;
            $this->dispatch('toast', message: 'Stock moved to disposal area.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    // ─── Complete ─────────────────────────────────────────────────────────────

    public function openCompleteModal(): void { $this->showCompleteModal = true; }
    public function closeCompleteModal(): void { $this->showCompleteModal = false; }

    public function complete(DisposalWorkflow $workflow): void
    {
        $this->authorize('execute', $this->disposalRequest);

        $rules = [];
        if ($this->disposalRequest->method === DisposalMethod::Auction) {
            $rules['completionData.buyer_name'] = ['required', 'string'];
            $rules['completionData.sale_price']  = ['required', 'numeric', 'min:0'];
            $rules['completionData.sale_date']    = ['required', 'date'];
        } else {
            $rules['completionData.destroy_method'] = ['required', 'string'];
            $rules['completionData.destroy_date']   = ['required', 'date'];
            $rules['completionData.witness_name']   = ['required', 'string'];
        }

        $this->validate($rules);

        try {
            $workflow->complete($this->disposalRequest, $this->completionData, auth()->user());
            $this->disposalRequest->refresh();
            $this->showCompleteModal = false;
            $this->dispatch('toast', message: 'Disposal completed successfully.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        $this->disposalRequest->loadMissing([
            'lines.item.baseUom',
            'lines.batch',
            'lines.location',
            'createdBy',
            'approvedBy',
            'movedBy',
            'completedBy',
            'rejectedBy',
            'virtualWarehouse',
            'department',
            'approvals.user',
        ]);

        return view('livewire.inventory.disposals.show')
            ->layout('components.layouts.app', [
                'title'           => "Disposal {$this->disposalRequest->number}",
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
