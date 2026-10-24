<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\Orders;

use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\PickListStatus;
use App\Enums\Outbound\ContainerSize;
use App\Enums\Outbound\PackageType;
use App\Models\DeliveryOrder;
use App\Models\Inventory\SerialNumber;
use App\Models\Partner;
use App\Models\PickList;
use App\Models\PickTask;
use App\Services\Outbound\AllocationService;
use App\Services\Outbound\DeliveryOrderWorkflow;
use App\Services\Outbound\DispatchService;
use App\Services\Outbound\PickingService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public DeliveryOrder $deliveryOrder;  // route model binding

    public string $activeTab = 'lines';

    // Assign picker modal
    public bool   $showAssignModal = false;
    public string $assignUserId    = '';

    public function mount(DeliveryOrder $deliveryOrder): void
    {
        $this->authorize('view', $deliveryOrder);
    }

    #[Computed]
    public function do(): DeliveryOrder
    {
        return $this->deliveryOrder->load([
            'virtualWarehouse', 'warehouse', 'department',
            'consigneePartner', 'requestedBy',
            'lines.item.baseUom', 'lines.uom', 'lines.allocations.location', 'lines.allocations.batch',
            'approvals.decidedBy',
            'pickLists.assignedTo',
            'pickLists.tasks.location',
            'pickLists.tasks.item.baseUom',
            'pickLists.tasks.batch',
            'pickLists.tasks.pickedBy',
            'dispatches.gatePass', 'dispatches.dispatchedBy',
            'proofOfDelivery',
        ]);
    }

    #[Computed]
    public function stepperStatuses(): array
    {
        return [
            DeliveryOrderStatus::Draft,
            DeliveryOrderStatus::Approved,
            DeliveryOrderStatus::Acceptance,
            DeliveryOrderStatus::Allocated,
            DeliveryOrderStatus::Picking,
            DeliveryOrderStatus::Dispatched,
        ];
    }

    public function stepperCurrentIndex(): int
    {
        // Index = the step currently highlighted (amber).
        // Steps before it are green (done). Steps after are grey (future).
        // Stepper: 0=Draft, 1=Approved, 2=Acceptance, 3=Allocated, 4=Picking, 5=Dispatched
        return match($this->deliveryOrder->status) {
            DeliveryOrderStatus::Draft,
            DeliveryOrderStatus::Rejected,
            DeliveryOrderStatus::Cancelled                    => 0,
            DeliveryOrderStatus::Submitted,
            DeliveryOrderStatus::ReturnedForCorrection,
            DeliveryOrderStatus::AwaitingRequesterCorrection  => 1,
            DeliveryOrderStatus::Approved                     => 2,
            DeliveryOrderStatus::Acceptance,
            DeliveryOrderStatus::Assigned                     => 3,
            DeliveryOrderStatus::Allocated,
            DeliveryOrderStatus::PartiallyAllocated           => 4,
            DeliveryOrderStatus::Picking,
            DeliveryOrderStatus::Picked                       => 5,
            DeliveryOrderStatus::Dispatched,
            DeliveryOrderStatus::Delivered                    => 6,
        };
    }

    #[Computed]
    public function activePickList(): ?PickList
    {
        return $this->deliveryOrder->pickLists()
            ->whereIn('status', [PickListStatus::Open->value, PickListStatus::InProgress->value])
            ->first();
    }

    #[Computed]
    public function pickerUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return User::where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->where('name', 'picker'))
            ->orderBy('name')
            ->get();
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

    // Pick task modal (inline picking from DO page)
    public bool   $showPickTaskModal = false;
    public ?int   $activePickTaskId  = null;
    public string $pickedQty         = '';
    public array  $availableSerials  = [];
    public array  $selectedSerials   = [];

    // Dispatch modal
    public bool   $showDispatchModal     = false;
    public string $vehicleRegNo          = '';
    public string $containerNo           = '';
    public string $containerSize         = '';
    public string $sealNo                = '';
    public string $driverName            = '';
    public string $driverPhone           = '';
    public string $driverIdNo            = '';
    public string $transporterPartnerId  = '';
    public string $dispatchNotes         = '';
    public bool   $createBackorder       = false;

    // Return-for-correction modal
    public bool   $showReturnModal          = false;
    public string $returnNotes              = '';
    public bool   $showWarehouseReturnModal = false;
    public string $warehouseReturnNotes     = '';

    // Reject modal
    public bool   $showRejectModal  = false;
    public string $rejectNotes      = '';

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function submit(): void
    {
        $this->authorize('submit', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->submit($this->deliveryOrder, auth()->user());
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order submitted for approval.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function approve(): void
    {
        $this->authorize('approveDepartment', $this->deliveryOrder);
        try {
            // Find the current pending level and approve it
            $pending = $this->deliveryOrder->approvals()
                ->whereNull('decision')
                ->orderBy('level')
                ->first();
            $level = $pending?->level ?? 1;
            app(DeliveryOrderWorkflow::class)->approve($this->deliveryOrder, auth()->user(), $level);
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order approved.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function returnForCorrection(): void
    {
        $this->validate(['returnNotes' => ['required', 'string', 'min:5']]);
        $this->authorize('returnOrder', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->returnForCorrection($this->deliveryOrder, auth()->user(), $this->returnNotes);
            $this->showReturnModal = false;
            $this->returnNotes     = '';
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order returned for correction.', type: 'info');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function warehouseSendBack(): void
    {
        $this->validate(['warehouseReturnNotes' => ['required', 'string', 'min:5']]);
        $this->authorize('returnFromWarehouse', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->returnFromWarehouse($this->deliveryOrder, auth()->user(), $this->warehouseReturnNotes);
            $this->showWarehouseReturnModal = false;
            $this->warehouseReturnNotes     = '';
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order sent back for correction.', type: 'info');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function forwardToRequester(): void
    {
        $this->authorize('forwardReturn', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->forwardToRequester($this->deliveryOrder, auth()->user());
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order sent back to requester.', type: 'info');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function rejectOrder(): void
    {
        $this->validate(['rejectNotes' => ['required', 'string', 'min:5']]);
        $this->authorize('approveDepartment', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->reject($this->deliveryOrder, auth()->user(), $this->rejectNotes);
            $this->showRejectModal = false;
            $this->rejectNotes     = '';
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order rejected.', type: 'warning');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function accept(): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->accept($this->deliveryOrder, auth()->user());
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order accepted for processing.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function assignTeam(): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->assignTeam($this->deliveryOrder, auth()->user());
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order assigned to warehouse team.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function allocate(): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        try {
            $warnings = app(AllocationService::class)->allocate($this->deliveryOrder);
            $this->deliveryOrder->refresh();
            unset($this->do);

            if (empty($warnings)) {
                $this->dispatch('toast', message: 'Allocation complete.', type: 'success');
            } else {
                $this->dispatch('toast', message: 'Partially allocated. ' . implode(' ', $warnings), type: 'warning');
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function generatePickList(): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        try {
            $assigned = $this->assignUserId !== '' ? User::find($this->assignUserId) : null;
            app(PickingService::class)->generatePickList($this->deliveryOrder, $assigned);
            $this->showAssignModal = false;
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Pick list generated.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function resumePicking(): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        try {
            $warnings = app(AllocationService::class)->resumeFromShort($this->deliveryOrder);
            $this->deliveryOrder->refresh();
            unset($this->do, $this->activePickList);
            if (empty($warnings)) {
                $this->dispatch('toast', message: 'Stock re-allocated. Generate a new pick list to continue.', type: 'success');
            } else {
                $this->dispatch('toast', message: 'Partially re-allocated. ' . implode(' ', $warnings), type: 'warning');
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openDispatchModal(): void
    {
        $this->vehicleRegNo         = '';
        $this->containerNo          = '';
        $this->containerSize        = '';
        $this->sealNo               = '';
        $this->driverName           = '';
        $this->driverPhone          = '';
        $this->driverIdNo           = '';
        $this->transporterPartnerId = '';
        $this->dispatchNotes        = '';
        $this->createBackorder      = $this->deliveryOrder->lines()
            ->whereColumn('picked_qty', '<', 'requested_qty_base')
            ->exists();
        $this->showDispatchModal    = true;
    }

    public function dispatchOrder(): void
    {
        $this->authorize('allocate', $this->deliveryOrder);

        $packageType = $this->deliveryOrder->package_type?->value ?? '';
        $isContainer = $packageType === PackageType::Container->value;

        $this->validate([
            'vehicleRegNo'  => ['required', 'string', 'max:20'],
            'driverName'    => ['required', 'string', 'max:100'],
            'driverPhone'   => ['nullable', 'string', 'regex:/^(\+?255|0)7[0-9]{8}$/'],
            'driverIdNo'    => ['nullable', 'string', 'max:50'],
            'sealNo'        => $isContainer ? ['required', 'string', 'max:50'] : ['nullable', 'string', 'max:50'],
            'containerNo'   => $isContainer ? ['required', 'string', 'max:50'] : ['nullable', 'string', 'max:50'],
            'containerSize' => $isContainer ? ['required', 'string'] : ['nullable', 'string'],
        ], [
            'driverPhone.regex'      => 'Enter a valid Tanzanian phone number (e.g. 0712345678).',
            'sealNo.required'        => 'Seal No is required for container shipments.',
            'containerNo.required'   => 'Container No is required for container shipments.',
            'containerSize.required' => 'Container Size is required for container shipments.',
        ]);

        $hadShortLines = $this->createBackorder && $this->deliveryOrder->lines()
            ->whereColumn('picked_qty', '<', 'requested_qty_base')
            ->exists();

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
            $dispatch = app(DispatchService::class)->confirmDispatch(
                $this->deliveryOrder,
                $data,
                auth()->user(),
                $this->createBackorder,
            );
            $this->showDispatchModal = false;
            $this->deliveryOrder->refresh();
            unset($this->do);

            $msg = "Dispatched as {$dispatch->number}. Gate pass issued.";
            if ($hadShortLines) {
                $msg .= ' Backorder created for remaining items.';
            }
            $this->dispatch('toast', message: $msg, type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function cancel(): void
    {
        $this->authorize('cancel', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->cancel($this->deliveryOrder, auth()->user());
            $this->deliveryOrder->refresh();
            unset($this->do);
            $this->dispatch('toast', message: 'Order cancelled.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public bool $showDeleteModal = false;

    public function deleteOrder(): void
    {
        $this->authorize('delete', $this->deliveryOrder);
        try {
            app(DeliveryOrderWorkflow::class)->adminDelete($this->deliveryOrder, auth()->user());
            $this->showDeleteModal = false;
            $this->dispatch('toast', message: 'Order deleted.', type: 'success');
            $this->redirectRoute('outbound.orders.index');
        } catch (\Throwable $e) {
            $this->showDeleteModal = false;
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function cancelPickList(int $pickListId): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        try {
            $pickList = PickList::findOrFail($pickListId);
            app(PickingService::class)->cancelPickList($pickList);
            $this->deliveryOrder->refresh();
            unset($this->do, $this->activePickList);
            $this->dispatch('toast', message: 'Pick list cancelled. You may regenerate.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function openPickTask(int $taskId): void
    {
        $task = PickTask::with(['allocation.item', 'allocation.location', 'allocation.batch'])->find($taskId);

        $this->activePickTaskId = $taskId;
        $this->pickedQty        = $task ? number_format((float) $task->qty_to_pick, 4, '.', '') : '';
        $this->availableSerials = [];
        $this->selectedSerials  = [];

        if ($task && $task->allocation?->item?->track_serial) {
            $alloc = $task->allocation;

            $rows = SerialNumber::with('location')
                ->where('item_id', $alloc->item_id)
                ->where('status', 'in_stock')
                ->orderByRaw('location_id = ? DESC', [$alloc->location_id])
                ->orderBy('serial_no')
                ->get();

            $this->availableSerials = $rows->map(fn ($s) => [
                'serial_no'         => $s->serial_no,
                'location_code'     => $s->location?->code ?? '—',
                'at_alloc_location' => $s->location_id === $alloc->location_id,
            ])->toArray();

            $atLocation = array_values(array_filter($this->availableSerials, fn ($s) => $s['at_alloc_location']));
            $qty        = (int) round((float) $task->qty_to_pick);
            $this->selectedSerials = array_column(array_slice($atLocation, 0, $qty), 'serial_no');
        }

        $this->showPickTaskModal = true;
    }

    public function confirmPick(): void
    {
        $this->validate(['pickedQty' => ['required', 'numeric', 'min:0.0001']]);

        $task    = PickTask::with(['allocation.item'])->findOrFail($this->activePickTaskId);
        $serials = array_values(array_filter($this->selectedSerials, fn ($s) => $s !== ''));

        if ($task->allocation?->item?->track_serial && count($serials) === 0) {
            $this->addError('selectedSerials', 'Select at least one serial number for this item.');
            return;
        }

        if ($task->allocation?->item?->track_serial) {
            $expected = (int) round((float) $this->pickedQty);
            if (count($serials) !== $expected) {
                $this->addError('selectedSerials', "Select exactly {$expected} serial(s) — got " . count($serials) . '.');
                return;
            }
        }

        try {
            app(PickingService::class)->executeTask(
                $task,
                number_format((float) $this->pickedQty, 4, '.', ''),
                auth()->user(),
                $serials,
            );
            $this->showPickTaskModal = false;
            $this->activePickTaskId  = null;
            $this->deliveryOrder->refresh();
            unset($this->do, $this->activePickList);
            $this->dispatch('toast', message: 'Task picked.', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function unconfirmTask(int $taskId): void
    {
        $this->authorize('allocate', $this->deliveryOrder);
        try {
            $task = PickTask::with(['stockTransaction', 'allocation.stockBalance'])->findOrFail($taskId);
            app(PickingService::class)->unconfirmTask($task, auth()->user());
            $this->deliveryOrder->refresh();
            unset($this->do, $this->activePickList);
            $this->dispatch('toast', message: 'Task reversed — stock returned to bin.', type: 'info');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        return view('livewire.outbound.orders.show')
            ->layout('components.layouts.app', [
                'title'           => $this->deliveryOrder->number,
                'breadcrumbGroup' => 'Outbound',
            ]);
    }
}
