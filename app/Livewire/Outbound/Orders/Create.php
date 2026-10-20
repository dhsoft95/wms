<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\Orders;

use App\Actions\Outbound\CreateDeliveryOrderAction;
use App\Enums\Outbound\AllocationRule;
use App\Enums\Outbound\DeliveryOrderPriority;
use App\Models\Department;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Partner;
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
    public function totalQty(): int|float
    {
        return array_sum(array_map('floatval', array_column($this->lines, 'qty')));
    }

    // ─── Line Management ──────────────────────────────────────────────────────

    public function updatedLines(mixed $value, ?string $key): void
    {
        if ($key === null) {
            return;
        }

        if (str_ends_with($key, '.item_sku')) {
            $index = (int) explode('.', $key)[0];
            $query = trim((string) $value);
            if ($query === '') {
                $this->lines[$index]['search_results'] = [];
            } else {
                $this->liveSearch($index, $query);
            }
        }
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

    public function liveSearch(int $lineIndex, string $query): void
    {
        if (trim($query) === '') {
            $this->lines[$lineIndex]['search_results'] = [];
            return;
        }

        $this->lines[$lineIndex]['search_results'] = Item::where('is_active', true)
            ->where(fn ($q) => $q
                ->where('sku', 'like', '%' . $query . '%')
                ->orWhere('name', 'like', '%' . $query . '%')
            )
            ->with('baseUom')
            ->limit(8)
            ->get()
            ->map(fn ($item) => [
                'id'       => (string) $item->id,
                'sku'      => $item->sku,
                'name'     => $item->name,
                'uom_id'   => (string) ($item->baseUom?->id ?? ''),
                'uom_code' => $item->baseUom?->code ?? '',
            ])
            ->toArray();
    }

    public function selectItem(int $lineIndex, int $itemId): void
    {
        $item = Item::where('is_active', true)->with('baseUom')->find($itemId);
        if ($item === null) {
            return;
        }

        // Sum available qty across all balances for this item in the selected VW
        $availableQty = null;
        if ($this->virtualWarehouseId !== '') {
            $availableQty = StockBalance::where('item_id', $item->id)
                ->where('virtual_warehouse_id', $this->virtualWarehouseId)
                ->where('stock_status', \App\Enums\Inventory\StockStatus::Available->value)
                ->get()
                ->sum(fn ($b) => (float) $b->qtyAvailable());
        }

        $this->lines[$lineIndex]['item_id']       = (string) $item->id;
        $this->lines[$lineIndex]['item_name']      = $item->name;
        $this->lines[$lineIndex]['item_sku']       = $item->sku;
        $this->lines[$lineIndex]['uom_id']         = (string) ($item->baseUom?->id ?? '');
        $this->lines[$lineIndex]['uom_code']       = $item->baseUom?->code ?? '';
        $this->lines[$lineIndex]['available_qty']  = $availableQty;
        $this->lines[$lineIndex]['search_results'] = [];
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

        // Stock availability check on every save
        foreach ($this->lines as $i => $line) {
            $available = StockBalance::where('item_id', $line['item_id'])
                ->where('virtual_warehouse_id', $this->virtualWarehouseId)
                ->where('stock_status', \App\Enums\Inventory\StockStatus::Available->value)
                ->get()
                ->sum(fn ($b) => (float) $b->qtyAvailable());

            if ($available <= 0) {
                $this->addError("lines.{$i}.qty", "No stock available for {$line['item_sku']}.");
                return;
            }
            if ((float) $line['qty'] > $available) {
                $this->addError("lines.{$i}.qty", "Qty exceeds available stock ({$available} {$line['uom_code']}) for {$line['item_sku']}.");
                return;
            }
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
            'item_id'        => '',
            'uom_id'         => '',
            'qty'            => 1,
            'notes'          => '',
            'item_name'      => '',
            'item_sku'       => '',
            'uom_code'       => '',
            'available_qty'  => null,
            'search_results' => [],
        ];
    }
}
