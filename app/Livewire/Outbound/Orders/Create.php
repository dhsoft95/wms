<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\Orders;

use App\Actions\Outbound\CreateDeliveryOrderAction;
use App\Enums\Outbound\AllocationRule;
use App\Enums\Outbound\DeliveryOrderPriority;
use App\Enums\Inventory\StockStatus;
use App\Models\Department;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Partner;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

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

    public function mount(): void
    {
        $this->lines = [$this->blankLine()];
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
    public function items(): \Illuminate\Database\Eloquent\Collection
    {
        return Item::where('is_active', true)->with('baseUom')->orderBy('name')->get();
    }

    #[Computed]
    public function uoms(): \Illuminate\Database\Eloquent\Collection
    {
        return UnitOfMeasure::orderBy('code')->get(['id', 'code', 'name']);
    }

    #[Computed]
    public function totalQty(): int|float
    {
        return array_sum(array_map('floatval', array_column($this->lines, 'qty')));
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
        $this->validate([
            'virtualWarehouseId'   => ['required', 'exists:virtual_warehouses,id'],
            'warehouseId'          => ['required', 'exists:warehouses,id'],
            'requiredDate'         => ['required', 'date', 'after_or_equal:today'],
            'priority'             => ['required', Rule::in(array_column(DeliveryOrderPriority::cases(), 'value'))],
            'allocationRule'       => ['required', Rule::in(array_column(AllocationRule::cases(), 'value'))],
            'mrnNumber'            => ['required', 'string', 'max:100'],
            'mrnFile'              => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'lines'                => ['required', 'array', 'min:1'],
            'lines.*.item_id'      => ['required', 'exists:items,id'],
            'lines.*.qty'          => ['required', 'numeric', 'min:0.0001'],
            'lines.*.uom_id'       => ['required', 'exists:units_of_measure,id'],
        ]);

        // Stock validation — only count allocatable stock (pickable locations, available status)
        $stockErrors = [];
        foreach ($this->lines as $line) {
            if ((string) ($line['item_id'] ?? '') === '') {
                continue;
            }
            $itemLabel = trim(($line['item_sku'] ?? '') . ' ' . ($line['item_name'] ?? '')) ?: "Item #{$line['item_id']}";
            [$available, $warning] = $this->checkLineStock((int) $line['item_id']);

            if ($available <= 0) {
                $stockErrors[] = "{$itemLabel}: " . ($warning ?? 'No allocatable stock available.');
            } elseif ((float) $line['qty'] > $available) {
                $uom = $line['uom_code'] ?? '';
                $stockErrors[] = "{$itemLabel}: Requested {$line['qty']} {$uom} exceeds available {$available} {$uom}.";
            }
        }

        if (! empty($stockErrors)) {
            $this->dispatch('stock-errors', errors: $stockErrors);
            return;
        }

        $mrnFilePath = $this->mrnFile?->store('delivery_orders/mrn', 'local');

        $do = app(CreateDeliveryOrderAction::class)->execute(
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
                'mrn_number'           => $this->mrnNumber,
                'mrn_file_path'        => $mrnFilePath,
                'auto_submit'          => $this->autoSubmit,
                'lines'                => $this->lines,
            ],
            auth()->user(),
        );

        $this->dispatch('toast', message: "Order {$do->number} created.", type: 'success');
        $this->redirect(route('outbound.orders.show', $do), navigate: true);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.outbound.orders.create')
            ->layout('components.layouts.app', [
                'title'           => 'New Delivery Order',
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
            'stock_warning' => null,
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

        [$available, $warning] = $this->checkLineStock($itemId);
        $this->lines[$index]['available_qty'] = $this->virtualWarehouseId !== '' ? $available : null;
        $this->lines[$index]['stock_warning'] = $warning;
    }

    private function clearLineItem(int $index): void
    {
        $this->lines[$index]['item_name']     = '';
        $this->lines[$index]['item_sku']      = '';
        $this->lines[$index]['uom_id']        = '';
        $this->lines[$index]['uom_code']      = '';
        $this->lines[$index]['available_qty'] = null;
        $this->lines[$index]['stock_warning'] = null;
    }

    /**
     * @return array{0: float, 1: string|null}  [available_qty, warning_message]
     */
    private function checkLineStock(int $itemId): array
    {
        if ($this->virtualWarehouseId === '') {
            return [0.0, null];
        }

        $pickable = ['storage', 'staging', 'hazardous'];
        $vwId     = $this->virtualWarehouseId;

        $available = StockBalance::where('item_id', $itemId)
            ->where('virtual_warehouse_id', $vwId)
            ->where('stock_status', StockStatus::Available->value)
            ->whereHas('location', fn ($q) => $q->whereIn('type', $pickable)->whereIn('status', ['active', 'full']))
            ->get()
            ->sum(fn ($b) => (float) $b->qtyAvailable());

        if ($available > 0) {
            return [$available, null];
        }

        // Diagnose why no allocatable stock exists
        $anyStock = StockBalance::where('item_id', $itemId)
            ->where('virtual_warehouse_id', $vwId)
            ->where('qty_on_hand', '>', 0)
            ->exists();

        if (! $anyStock) {
            return [0.0, 'No stock in this virtual warehouse.'];
        }

        $inDock = StockBalance::where('item_id', $itemId)
            ->where('virtual_warehouse_id', $vwId)
            ->whereRaw('qty_on_hand - qty_allocated > 0')
            ->whereHas('location', fn ($q) => $q->whereNotIn('type', $pickable))
            ->exists();

        if ($inDock) {
            return [0.0, 'Putaway not complete — stock is still in the receiving dock.'];
        }

        $onHold = StockBalance::where('item_id', $itemId)
            ->where('virtual_warehouse_id', $vwId)
            ->whereRaw('qty_on_hand - qty_allocated > 0')
            ->whereHas('location', fn ($q) => $q->whereIn('type', $pickable))
            ->where('stock_status', '!=', StockStatus::Available->value)
            ->exists();

        if ($onHold) {
            return [0.0, 'Stock is on hold or obsolete.'];
        }

        return [0.0, 'All available stock is already reserved by other orders.'];
    }
}
