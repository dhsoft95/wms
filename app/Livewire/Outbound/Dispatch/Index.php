<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\Dispatch;

use App\Enums\Outbound\ContainerSize;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\PackageType;
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

    public string $search = '';
    public string $tab    = 'ready';   // 'ready' | 'recent'

    // Dispatch modal
    public bool    $showDispatchModal    = false;
    public ?int    $actionDoId           = null;
    public string  $packageType          = '';   // read-only display, loaded from DO
    public string  $vehicleRegNo         = '';
    public string  $containerNo          = '';
    public string  $containerSize        = '';
    public string  $sealNo               = '';
    public string  $driverName           = '';
    public string  $driverPhone          = '';
    public string  $driverIdNo           = '';
    public string  $transporterPartnerId = '';
    public string  $dispatchNotes        = '';

    // Gate verify modal
    public bool  $showGateModal  = false;
    public ?int  $actionGatePassId = null;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedTab(): void    { $this->resetPage(); }

    #[Computed]
    public function readyOrders(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = DeliveryOrder::with(['consigneePartner', 'virtualWarehouse', 'warehouse'])
            ->where('status', DeliveryOrderStatus::Picked->value);

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
        return Partner::ofType('transporter')->where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function containerSizes(): array
    {
        return ContainerSize::cases();
    }

    public function isContainer(): bool
    {
        return $this->packageType === PackageType::Container->value;
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function openDispatchModal(int $doId): void
    {
        $do = DeliveryOrder::findOrFail($doId);

        $this->actionDoId           = $doId;
        $this->packageType          = $do->package_type?->value ?? '';
        $this->vehicleRegNo         = '';
        $this->containerNo          = '';
        $this->containerSize        = '';
        $this->sealNo               = '';
        $this->driverName           = '';
        $this->driverPhone          = '';
        $this->driverIdNo           = '';
        $this->transporterPartnerId = '';
        $this->dispatchNotes        = '';
        $this->showDispatchModal    = true;
    }

    public function closeDispatchModal(): void
    {
        $this->showDispatchModal = false;
        $this->actionDoId        = null;
    }

    public function confirmDispatch(): void
    {
        $isContainer = $this->packageType === PackageType::Container->value;

        $rules = [
            'vehicleRegNo'  => ['required', 'string', 'max:20'],
            'driverName'    => ['required', 'string', 'max:100'],
            'driverPhone'   => ['nullable', 'string', 'regex:/^(\+?255|0)7[0-9]{8}$/'],
            'driverIdNo'    => ['nullable', 'string', 'max:50'],
            'sealNo'        => $isContainer ? ['required', 'string', 'max:50'] : ['nullable', 'string', 'max:50'],
            'containerNo'   => $isContainer ? ['required', 'string', 'max:50'] : ['nullable', 'string', 'max:50'],
            'containerSize' => $isContainer ? ['required', 'string'] : ['nullable', 'string'],
        ];

        $messages = [
            'driverPhone.regex'   => 'Enter a valid Tanzanian phone number (e.g. 0712345678 or +255712345678).',
            'sealNo.required'     => 'Seal No is required for container shipments.',
            'containerNo.required'=> 'Container No is required for container shipments.',
            'containerSize.required' => 'Container Size is required for container shipments.',
        ];

        $this->validate($rules, $messages);

        $do = DeliveryOrder::findOrFail($this->actionDoId);
        $this->authorize('allocate', $do);

        $data = [
            'vehicle_reg_no'         => strtoupper(trim($this->vehicleRegNo)),
            'container_no'           => $isContainer ? strtoupper(trim($this->containerNo)) : null,
            'container_size'         => $isContainer ? ($this->containerSize ?: null) : null,
            'seal_no'                => $this->sealNo ?: null,
            'driver_name'            => $this->driverName,
            'driver_phone'           => $this->driverPhone ?: null,
            'driver_id_no'           => $this->driverIdNo ?: null,
            'transporter_partner_id' => $this->transporterPartnerId ?: null,
            'notes'                  => $this->dispatchNotes ?: null,
        ];

        try {
            $dispatch = app(DispatchService::class)->confirmDispatch($do, $data, auth()->user());
        } catch (\DomainException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
            return;
        }

        $this->showDispatchModal = false;
        $this->actionDoId        = null;
        $this->dispatch('toast', message: "Dispatch {$dispatch->number} confirmed. Gate pass issued.", type: 'success');
        $this->tab = 'recent';
        unset($this->readyOrders, $this->recentDispatches);
    }

    public function verifyGateExit(int $gatePassId): void
    {
        $this->authorize('gate.verify');
        try {
            app(DispatchService::class)->verifyGateExit(
                \App\Models\GatePass::findOrFail($gatePassId),
                auth()->user()
            );
        } catch (\DomainException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
            return;
        }
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
