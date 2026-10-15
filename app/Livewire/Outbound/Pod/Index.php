<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\Pod;

use App\Models\DeliveryOrder;
use App\Models\ProofOfDelivery;
use App\Services\Outbound\DeliveryOrderWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination, WithFileUploads;

    public string $tab    = 'awaiting';
    public string $search = '';

    // POD upload modal
    public bool    $showPodModal   = false;
    public ?int    $actionDoId     = null;
    public string  $receivedBy     = '';
    public string  $receivedAt     = '';
    public string  $condition      = 'good';
    public string  $podNotes       = '';
    public $podFile                = null;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedTab(): void    { $this->resetPage(); }

    #[Computed]
    public function awaitingOrders(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = DeliveryOrder::with(['consigneePartner', 'dispatches'])
            ->where('status', \App\Enums\Outbound\DeliveryOrderStatus::Dispatched->value);

        $user = auth()->user();
        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            if (! empty($vwIds)) {
                $query->whereIn('virtual_warehouse_id', $vwIds);
            }
        }

        if ($this->search !== '') {
            $s = '%' . $this->search . '%';
            $query->where(fn ($q) => $q->where('number', 'like', $s)
                ->orWhereHas('consigneePartner', fn ($sq) => $sq->where('name', 'like', $s)));
        }

        return $query->orderBy('required_date')->paginate(20);
    }

    #[Computed]
    public function deliveredOrders(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = DeliveryOrder::with(['consigneePartner', 'proofOfDelivery'])
            ->where('status', \App\Enums\Outbound\DeliveryOrderStatus::Delivered->value);

        $user = auth()->user();
        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            if (! empty($vwIds)) {
                $query->whereIn('virtual_warehouse_id', $vwIds);
            }
        }

        if ($this->search !== '') {
            $s = '%' . $this->search . '%';
            $query->where(fn ($q) => $q->where('number', 'like', $s)
                ->orWhereHas('consigneePartner', fn ($sq) => $sq->where('name', 'like', $s)));
        }

        return $query->orderByDesc('updated_at')->paginate(20);
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function openPodModal(int $doId): void
    {
        $this->actionDoId   = $doId;
        $this->receivedBy   = '';
        $this->receivedAt   = now()->format('Y-m-d\TH:i');
        $this->podNotes     = '';
        $this->podFile      = null;
        $this->showPodModal = true;
    }

    public function closePodModal(): void
    {
        $this->showPodModal = false;
        $this->actionDoId   = null;
    }

    public function confirmPod(): void
    {
        $this->validate([
            'receivedBy' => ['required', 'string', 'max:100'],
            'receivedAt' => ['required', 'date'],
            'podFile'    => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $do = DeliveryOrder::findOrFail($this->actionDoId);
        $this->authorize('uploadPod', $do);

        $filePaths = [];
        if ($this->podFile !== null) {
            $filePaths[] = $this->podFile->store('pod-files', 'public');
        }

        $podData = [
            'received_by_name' => $this->receivedBy,
            'received_at'      => $this->receivedAt,
            'notes'            => $this->podNotes ?: null,
            'file_paths'       => $filePaths,
            'disk'             => 'public',
        ];

        app(DeliveryOrderWorkflow::class)->recordPod($do, $podData, auth()->user());

        $this->showPodModal = false;
        $this->actionDoId   = null;
        $this->dispatch('toast', message: "POD recorded for order {$do->number}.", type: 'success');
        unset($this->awaitingOrders, $this->deliveredOrders);
    }

    public function render(): View
    {
        return view('livewire.outbound.pod.index')
            ->layout('components.layouts.app', [
                'title'           => 'Proof of Delivery',
                'breadcrumbGroup' => 'Outbound',
            ]);
    }
}
