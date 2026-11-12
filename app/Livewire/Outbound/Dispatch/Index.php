<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\Dispatch;

use App\Enums\Outbound\ContainerSize;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\PackageType;
use App\Enums\ScanResultType;
use App\Livewire\Concerns\HandlesScans;
use App\Models\DeliveryOrder;
use App\Models\Dispatch as DispatchModel;
use App\Models\Inventory\Lpn;
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
    use HandlesScans;

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
    public bool    $createBackorder      = false;

    // Gate verify modal
    public bool  $showGateModal  = false;
    public ?int  $actionGatePassId = null;

    // LPN load-check (populated when dispatch modal opens)
    public array $loadedLpnCodes   = [];
    public array $expectedLpnCodes = [];

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

    // ─── Scan ─────────────────────────────────────────────────────────────────

    public function processScan(): void
    {
        $result = $this->resolveScan();
        if ($result === null) return;

        if (! $result->ok()) {
            $this->scanFail($result->error ?? 'Barcode not recognised.');
            return;
        }

        match ($result->type) {
            ScanResultType::DispatchOrder => $this->openDispatchForScan($result->record),
            ScanResultType::Dispatch      => $this->openDispatchByDsp($result->record),
            ScanResultType::Lpn           => $this->checkLpn($result->record),
            default                       => $this->scanFail('Not a dispatch order or LPN barcode.'),
        };
    }

    private function openDispatchForScan(?DeliveryOrder $do): void
    {
        if (! $do) {
            $this->scanFail('Delivery order not found.');
            return;
        }
        $this->openDispatchModal($do->getKey());
        $this->scanOk("DO {$do->number} opened.");
    }

    private function openDispatchByDsp(?DispatchModel $dispatch): void
    {
        if (! $dispatch || ! $dispatch->delivery_order_id) {
            $this->scanFail('Dispatch not found.');
            return;
        }
        $this->openDispatchModal($dispatch->delivery_order_id);
        $this->scanOk("Dispatch {$dispatch->number} opened.");
    }

    private function checkLpn(?Lpn $lpn): void
    {
        if (! $this->showDispatchModal) {
            $this->scanFail('Open a dispatch first, then scan LPNs.');
            return;
        }
        if (! $lpn) {
            $this->scanFail('LPN not found.');
            return;
        }
        if (! in_array($lpn->code, $this->loadedLpnCodes, true)) {
            $this->loadedLpnCodes[] = $lpn->code;
        }
        $this->scanOk("LPN {$lpn->code} loaded.");
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function openDispatchModal(int $doId): void
    {
        $do = DeliveryOrder::with('lines')->findOrFail($doId);

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
        $this->createBackorder      = $do->lines->some(
            fn ($l) => bccomp((string) $l->picked_qty, (string) $l->requested_qty_base, 4) < 0
        );
        $this->loadedLpnCodes       = [];
        $this->showDispatchModal    = true;
        unset($this->actionDo);
    }

    #[Computed]
    public function actionDo(): ?DeliveryOrder
    {
        if ($this->actionDoId === null) {
            return null;
        }
        return DeliveryOrder::with(['lines.item', 'lines.uom'])->find($this->actionDoId);
    }

    public function closeDispatchModal(): void
    {
        $this->showDispatchModal = false;
        $this->actionDoId        = null;
        $this->loadedLpnCodes    = [];
        unset($this->actionDo);
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

        $hadShortLines = $this->createBackorder && $do->lines->some(
            fn ($l) => bccomp((string) $l->picked_qty, (string) $l->requested_qty_base, 4) < 0
        );

        try {
            $dispatch = app(DispatchService::class)->confirmDispatch(
                $do, $data, auth()->user(), $this->createBackorder
            );
        } catch (\DomainException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
            return;
        }

        $this->showDispatchModal = false;
        $this->actionDoId        = null;
        unset($this->actionDo);

        $msg = "Dispatch {$dispatch->number} confirmed. Gate pass issued.";
        if ($hadShortLines) {
            $msg .= ' Backorder created for remaining items.';
        }
        $this->dispatch('toast', message: $msg, type: 'success');
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
