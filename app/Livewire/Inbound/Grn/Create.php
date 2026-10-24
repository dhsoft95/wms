<?php

declare(strict_types=1);

namespace App\Livewire\Inbound\Grn;

use App\Enums\GrnSource;
use App\Enums\GrnStatus;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\Item;
use App\Models\Location;
use App\Models\Partner;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Foundation\DocumentNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Create extends Component
{
    // Header fields
    public string  $source           = 'supplier';
    public string  $warehouseId      = '';
    public string  $supplierId       = '';
    public string  $customerId       = '';
    public string  $dockId           = '';
    public string  $deliveryNoteRef  = '';
    public string  $expectedDate     = '';
    public string  $notes            = '';

    // Expected lines
    public array $lines = [];

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->authorize('create', Grn::class);
        $first = Warehouse::active()->first();
        if ($first) {
            $this->warehouseId = (string) $first->id;
            $this->autofillDock();
        }
    }

    public function updatedWarehouseId(): void
    {
        $this->dockId = '';
        $this->autofillDock();
    }

    private function autofillDock(): void
    {
        if ($this->warehouseId === '') return;
        $warehouse = Warehouse::find((int) $this->warehouseId);
        if ($warehouse?->default_receiving_dock_location_id) {
            $this->dockId = (string) $warehouse->default_receiving_dock_location_id;
        }
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function sources(): array
    {
        return GrnSource::cases();
    }

    #[Computed]
    public function warehouses(): \Illuminate\Database\Eloquent\Collection
    {
        return Warehouse::active()->orderBy('name')->get();
    }

    #[Computed]
    public function suppliers(): \Illuminate\Database\Eloquent\Collection
    {
        return Partner::ofType('supplier')->active()->orderBy('name')->get();
    }

    #[Computed]
    public function customers(): \Illuminate\Database\Eloquent\Collection
    {
        return Partner::ofType('customer')->active()->orderBy('name')->get();
    }

    #[Computed]
    public function dockLocations(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->warehouseId === '') return collect();
        return Location::where('warehouse_id', (int) $this->warehouseId)
            ->where('type', 'receiving_dock')
            ->where('status', 'active')
            ->orderBy('code')
            ->get();
    }

    #[Computed]
    public function items(): \Illuminate\Database\Eloquent\Collection
    {
        return Item::active()->orderBy('name')->limit(500)->get();
    }

    #[Computed]
    public function uoms(): \Illuminate\Database\Eloquent\Collection
    {
        return UnitOfMeasure::active()->orderBy('code')->get();
    }

    #[Computed]
    public function virtualWarehouses(): \Illuminate\Database\Eloquent\Collection
    {
        return VirtualWarehouse::active()->orderBy('name')->get();
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function updatedLines(mixed $value, string $key): void
    {
        // Auto-fill UoM when item is selected on a line
        if (preg_match('/^(\d+)\.item_id$/', $key, $m)) {
            $i      = (int) $m[1];
            $itemId = (int) ($this->lines[$i]['item_id'] ?? 0);

            if ($itemId && empty($this->lines[$i]['uom_id'])) {
                $item = Item::find($itemId);
                if ($item?->base_uom_id) {
                    $this->lines[$i]['uom_id'] = (string) $item->base_uom_id;
                }
            }
        }
    }

    // ─── Lines ────────────────────────────────────────────────────────────────

    public function addLine(): void
    {
        $this->lines[] = [
            'item_id'             => '',
            'expected_qty'        => '',
            'uom_id'              => '',
            'virtual_warehouse_id'=> '',
            'batch_no'            => '',
            'expiry_date'         => '',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    // ─── Save ─────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->authorize('create', Grn::class);

        $this->validate([
            'source'                    => ['required', Rule::enum(GrnSource::class)],
            'warehouseId'               => ['required', 'exists:warehouses,id'],
            'supplierId'                => ['nullable', 'exists:partners,id'],
            'customerId'                => ['nullable', 'exists:partners,id'],
            'dockId'                    => ['nullable', 'exists:locations,id'],
            'deliveryNoteRef'           => ['nullable', 'string', 'max:100'],
            'expectedDate'              => ['nullable', 'date'],
            'notes'                     => ['nullable', 'string', 'max:2000'],
            'lines.*.item_id'           => ['nullable', 'exists:items,id'],
            'lines.*.expected_qty'      => ['required_with:lines.*.item_id', 'nullable', 'numeric', 'min:0.0001'],
            'lines.*.uom_id'            => ['required_with:lines.*.item_id', 'nullable', 'exists:units_of_measure,id'],
            'lines.*.virtual_warehouse_id' => ['required_with:lines.*.item_id', 'nullable', 'exists:virtual_warehouses,id'],
            'lines.*.batch_no'          => ['nullable', 'string', 'max:100'],
            'lines.*.expiry_date'       => ['nullable', 'date'],
        ]);

        app('db')->transaction(function () {
            $grn = Grn::create([
                'number'                     => DocumentNumber::next('GRN'),
                'source'                     => $this->source,
                'warehouse_id'               => (int) $this->warehouseId,
                'supplier_partner_id'        => $this->supplierId ?: null,
                'customer_partner_id'        => $this->customerId ?: null,
                'receiving_dock_location_id' => $this->dockId ?: null,
                'delivery_note_ref'          => $this->deliveryNoteRef ?: null,
                'expected_date'              => $this->expectedDate ?: null,
                'status'                     => GrnStatus::Draft->value,
                'received_by'                => auth()->id(),
                'notes'                      => $this->notes ?: null,
            ]);

            foreach ($this->lines as $i => $line) {
                if (empty($line['item_id'])) continue;

                $item = Item::find((int) $line['item_id']);
                GrnLine::create([
                    'grn_id'               => $grn->id,
                    'item_id'              => (int) $line['item_id'],
                    'virtual_warehouse_id' => (int) $line['virtual_warehouse_id'],
                    'uom_id'               => (int) $line['uom_id'],
                    'expected_qty'         => $line['expected_qty'],
                    'batch_no'             => $line['batch_no'] ?: null,
                    'expiry_date'          => $line['expiry_date'] ?: null,
                ]);
            }

            $this->dispatch('toast', message: "GRN {$grn->number} created.", type: 'success');
            $this->redirect(route('inbound.grn.show', $grn), navigate: true);
        });
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.inbound.grn.create')
            ->layout('components.layouts.app', [
                'title'           => 'New Goods Receipt',
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
