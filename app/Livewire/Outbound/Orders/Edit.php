<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\Orders;

use App\Actions\Outbound\UpdateDeliveryOrderAction;
use App\Enums\Outbound\AllocationRule;
use App\Enums\Outbound\DeliveryOrderPriority;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Models\Department;
use App\Models\DeliveryOrder;
use App\Models\Item;
use App\Models\Partner;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use WithFileUploads;
    // ─── Route Model Binding ──────────────────────────────────────────────────

    public DeliveryOrder $deliveryOrder;

    // ─── Form Properties ──────────────────────────────────────────────────────

    public string $virtualWarehouseId  = '';
    public string $warehouseId         = '';
    public string $departmentId        = '';
    public string $consigneePartnerId  = '';
    public string $deliveryAddress     = '';
    public string $requiredDate        = '';
    public string $priority            = 'normal';
    public string $allocationRule      = 'fefo';
    public string $packageType         = '';
    public string $remarks             = '';
    public string $mrnNumber           = '';
    public mixed  $mrnFile             = null;
    public array  $lines               = [];
    public bool   $autoSubmit          = false;

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(DeliveryOrder $deliveryOrder): void
    {
        $this->authorize('update', $deliveryOrder);

        $this->deliveryOrder = $deliveryOrder;

        $this->virtualWarehouseId = (string) ($deliveryOrder->virtual_warehouse_id ?? '');
        $this->warehouseId        = (string) ($deliveryOrder->warehouse_id ?? '');
        $this->departmentId       = (string) ($deliveryOrder->department_id ?? '');
        $this->consigneePartnerId = (string) ($deliveryOrder->consignee_partner_id ?? '');
        $this->deliveryAddress    = $deliveryOrder->delivery_address ?? '';
        $this->requiredDate       = $deliveryOrder->required_date
            ? $deliveryOrder->required_date->format('Y-m-d')
            : '';
        $this->priority           = $deliveryOrder->priority?->value ?? 'normal';
        $this->allocationRule     = $deliveryOrder->allocation_rule?->value ?? 'fefo';
        $this->packageType        = $deliveryOrder->package_type?->value ?? '';
        $this->remarks            = $deliveryOrder->remarks ?? '';
        $this->mrnNumber          = $deliveryOrder->mrn_number ?? '';

        $this->lines = $deliveryOrder->lines()
            ->with('item.baseUom', 'uom')
            ->orderBy('line_no')
            ->get()
            ->map(fn ($line) => [
                'item_id'       => (string) $line->item_id,
                'uom_id'        => (string) $line->uom_id,
                'qty'           => (float) $line->requested_qty,
                'notes'         => $line->notes ?? '',
                'item_name'     => $line->item?->name ?? '',
                'item_sku'      => $line->item?->sku ?? '',
                'uom_code'      => $line->uom?->code ?? '',
                'available_qty' => null,
            ])
            ->toArray();

        if (empty($this->lines)) {
            $this->lines = [$this->blankLine()];
        }
    }

    // ─── Computed Properties ──────────────────────────────────────────────────

    #[Computed]
    public function virtualWarehouses(): \Illuminate\Database\Eloquent\Collection
    {
        $query = VirtualWarehouse::where('is_active', true)->orderBy('name');

        if (! auth()->user()->canAccessAllVirtualWarehouses()) {
            $ids = auth()->user()->accessibleVirtualWarehouseIds();
            if (! empty($ids)) {
                $query->whereIn('id', $ids);
            }
        }

        return $query->get();
    }

    #[Computed]
    public function warehouses(): \Illuminate\Database\Eloquent\Collection
    {
        return Warehouse::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function departments(): \Illuminate\Database\Eloquent\Collection
    {
        return Department::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function partners(): \Illuminate\Database\Eloquent\Collection
    {
        return Partner::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function allocationRules(): array
    {
        return AllocationRule::cases();
    }

    #[Computed]
    public function priorities(): array
    {
        return DeliveryOrderPriority::cases();
    }

    #[Computed]
    public function totalQty(): int|float
    {
        return array_sum(array_column($this->lines, 'qty'));
    }

    #[Computed]
    public function items(): \Illuminate\Database\Eloquent\Collection
    {
        return Item::where('is_active', true)->with('baseUom')->orderBy('name')->get();
    }

    #[Computed]
    public function isDraft(): bool
    {
        return $this->deliveryOrder->status->isEditable();
    }

    // ─── Line Management ──────────────────────────────────────────────────────

    public function updatedLines(mixed $value, ?string $key): void
    {
        if ($key === null || ! str_ends_with($key, '.item_id')) {
            return;
        }

        $index = (int) explode('.', $key)[0];

        if ((string) $value === '') {
            $this->clearLineItem($index);
            return;
        }

        $this->fillLineFromItem($index, (int) $value);
    }

    public function addLine(): void
    {
        $this->lines[] = $this->blankLine();
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    // ─── Save ─────────────────────────────────────────────────────────────────

    public function save(): void
    {
        if (! $this->isDraft) {
            $this->dispatch('toast', message: 'Only draft orders can be edited.', type: 'error');
            return;
        }

        $this->validate([
            'virtualWarehouseId'   => ['required', 'exists:virtual_warehouses,id'],
            'warehouseId'          => ['required', 'exists:warehouses,id'],
            'requiredDate'         => ['required', 'date', 'after_or_equal:today'],
            'priority'             => ['required', Rule::in(array_column(DeliveryOrderPriority::cases(), 'value'))],
            'allocationRule'       => ['required', Rule::in(array_column(AllocationRule::cases(), 'value'))],
            'mrnNumber'            => ['nullable', 'string', 'max:100'],
            'mrnFile'              => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'lines'                => ['required', 'array', 'min:1'],
            'lines.*.item_id'      => ['required', 'exists:items,id'],
            'lines.*.qty'          => ['required', 'numeric', 'min:0.0001'],
            'lines.*.uom_id'       => ['required', 'exists:units_of_measure,id'],
        ]);

        $mrnFilePath = $this->deliveryOrder->mrn_file_path;
        if ($this->mrnFile) {
            if ($mrnFilePath) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($mrnFilePath);
            }
            $mrnFilePath = $this->mrnFile->store('delivery_orders/mrn', 'local');
        }

        $do = app(UpdateDeliveryOrderAction::class)->execute(
            $this->deliveryOrder,
            [
                'virtual_warehouse_id' => $this->virtualWarehouseId,
                'warehouse_id'         => $this->warehouseId,
                'department_id'        => $this->departmentId,
                'consignee_partner_id' => $this->consigneePartnerId,
                'delivery_address'     => $this->deliveryAddress,
                'required_date'        => $this->requiredDate,
                'priority'             => $this->priority,
                'allocation_rule'      => $this->allocationRule,
                'package_type'         => $this->packageType ?: null,
                'remarks'              => $this->remarks,
                'mrn_number'           => $this->mrnNumber ?: null,
                'mrn_file_path'        => $mrnFilePath,
                'lines'                => $this->lines,
            ],
        );

        $this->dispatch('toast', message: "Order {$do->number} updated.", type: 'success');
        $this->redirect(route('outbound.orders.show', $do), navigate: true);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.outbound.orders.edit')
            ->layout('components.layouts.app', [
                'title'           => 'Edit Delivery Order',
                'breadcrumbGroup' => 'Outbound',
            ]);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function blankLine(): array
    {
        return [
            'item_id'       => '',
            'uom_id'        => '',
            'qty'           => 1,
            'notes'         => '',
            'item_name'     => '',
            'item_sku'      => '',
            'uom_code'      => '',
            'available_qty' => null,
        ];
    }

    private function fillLineFromItem(int $index, int $itemId): void
    {
        $item = Item::where('is_active', true)->with('baseUom')->find($itemId);
        if ($item === null) {
            $this->clearLineItem($index);
            return;
        }

        $this->lines[$index]['item_name'] = $item->name;
        $this->lines[$index]['item_sku']  = $item->sku;
        $this->lines[$index]['uom_id']    = (string) ($item->baseUom?->id ?? '');
        $this->lines[$index]['uom_code']  = $item->baseUom?->code ?? '';
        $this->lines[$index]['available_qty'] = null;
    }

    private function clearLineItem(int $index): void
    {
        $this->lines[$index]['item_name']     = '';
        $this->lines[$index]['item_sku']      = '';
        $this->lines[$index]['uom_id']        = '';
        $this->lines[$index]['uom_code']      = '';
        $this->lines[$index]['available_qty'] = null;
    }
}
