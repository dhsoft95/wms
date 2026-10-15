<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\Dispatch;

use App\Enums\Outbound\DeliveryOrderStatus;
use App\Models\DeliveryOrder;
use App\Models\Dispatch as DispatchModel;
use App\Models\Partner;
use App\Services\Outbound\DispatchService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $tab          = 'ready';   // 'ready' | 'recent'

    // Dispatch modal
    public bool    $showDispatchModal   = false;
    public ?int    $actionDoId          = null;
    public string  $vehicleNo           = '';
    public string  $driverName          = '';
    public string  $driverPhone         = '';
    public string  $driverIdNo          = '';
    public string  $transporterPartnerId = '';
    public string  $sealNo              = '';
    public string  $dispatchNotes       = '';

    // Gate verify modal
    public bool   $showGateModal = false;
    public ?int   $actionGatePassId = null;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedTab(): void    { $this->resetPage(); }

    #[Computed]
    public function readyOrders(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = DeliveryOrder::with(['consigneePartner', 'virtualWarehouse', 'warehouse'])
            ->where('status', DeliveryOrderStatus::Picked->value);

        // VW scoping
        $user = auth()->user();
        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            if (! empty($vwIds)) {
                $query->whereIn('virtual_warehouse_id', $vwIds);
            }
        }

        if ($this->search !== '') {
            $s = '%' . $this->search . '%';
            $query->where(fn (Builder $q) => $q->where('number', 'like', $s)
                ->orWhereHas('consigneePartner', fn ($sq) => $sq->where('name', 'like', $s)));
        }

        return $query->orderBy('required_date')->paginate(20);
    }

    #[Computed]
    public function recentDispatches(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = DispatchModel::with(['deliveryOrder.consigneePartner', 'dispatchedBy', 'gatePass'])
            ->whereHas('deliveryOrder', function (Builder $q) {
                $user = auth()->user();
                if (! $user->canAccessAllVirtualWarehouses()) {
                    $vwIds = $user->accessibleVirtualWarehouseIds();
                    if (! empty($vwIds)) {
                        $q->whereIn('virtual_warehouse_id', $vwIds);
                    }
                }
            });

        if ($this->search !== '') {
            $s = '%' . $this->search . '%';
            $query->where(fn (Builder $q) => $q->where('number', 'like', $s)
                ->orWhereHas('deliveryOrder', fn ($sq) => $sq->where('number', 'like', $s)));
        }

        return $query->orderByDesc('dispatched_at')->paginate(20);
    }

    #[Computed]
    public function transporters(): \Illuminate\Database\Eloquent\Collection
    {
        return Partner::where('is_active', true)->orderBy('name')->get();
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function openDispatchModal(int $doId): void
    {
        $this->actionDoId            = $doId;
        $this->vehicleNo             = '';
        $this->driverName            = '';
        $this->driverPhone           = '';
        $this->driverIdNo            = '';
        $this->transporterPartnerId  = '';
        $this->sealNo                = '';
        $this->dispatchNotes         = '';
        $this->showDispatchModal     = true;
    }

    public function closeDispatchModal(): void
    {
        $this->showDispatchModal = false;
        $this->actionDoId        = null;
    }

    public function confirmDispatch(): void
    {
        $this->validate([
            'vehicleNo'   => ['required', 'string', 'max:20'],
            'driverName'  => ['required', 'string', 'max:100'],
            'driverPhone' => ['nullable', 'string', 'max:20'],
            'driverIdNo'  => ['nullable', 'string', 'max:50'],
        ]);

        $do = DeliveryOrder::findOrFail($this->actionDoId);
        $this->authorize('allocate', $do);  // reuse allocate permission for dispatch

        $data = [
            'vehicle_no'              => $this->vehicleNo,
            'driver_name'             => $this->driverName,
            'driver_phone'            => $this->driverPhone ?: null,
            'driver_id_no'            => $this->driverIdNo ?: null,
            'transporter_partner_id'  => $this->transporterPartnerId ?: null,
            'seal_no'                 => $this->sealNo ?: null,
            'notes'                   => $this->dispatchNotes ?: null,
        ];

        $dispatch = app(DispatchService::class)->confirmDispatch($do, $data, auth()->user());

        $this->showDispatchModal = false;
        $this->actionDoId        = null;
        $this->dispatch('toast', message: "Dispatch {$dispatch->number} confirmed. Gate pass issued.", type: 'success');
        $this->tab = 'recent';
        unset($this->readyOrders, $this->recentDispatches);
    }

    public function verifyGateExit(int $gatePassId): void
    {
        $this->authorize('gate.verify');
        app(DispatchService::class)->verifyGateExit(
            \App\Models\GatePass::findOrFail($gatePassId),
            auth()->user()
        );
        $this->dispatch('toast', message: 'Gate exit verified.', type: 'success');
        unset($this->recentDispatches);
    }

    public function render(): View
    {
        return view('livewire.outbound.dispatch.index')
            ->layout('components.layouts.app', [
                'title'           => 'Dispatch & Gate',
                'breadcrumbGroup' => 'Outbound',
            ]);
    }
}
