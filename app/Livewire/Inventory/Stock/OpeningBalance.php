<?php

namespace App\Livewire\Inventory\Stock;

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockPosting;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationLevel;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Services\Inventory\StockLedger;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

class OpeningBalance extends Component
{
    public string $notes = '';
    public array $lines = [];
    public bool $previewing = false;

    public function mount(): void
    {
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'item_id'      => '',
            'batch_no'     => '',
            'expiry_date'  => '',
            'location_id'  => '',
            'vw_id'        => '',
            'qty'          => '',
            'uom_id'       => '',
            'stock_status' => 'available',
            'serials'      => '',  // newline-separated serial numbers
        ];
    }

    public function removeLine(int $index): void
    {
        array_splice($this->lines, $index, 1);
        if (empty($this->lines)) {
            $this->addLine();
        }
    }

    public function preview(): void
    {
        $this->validate([
            'lines'                  => 'required|array|min:1',
            'lines.*.item_id'        => 'required|exists:items,id',
            'lines.*.location_id'    => 'required|exists:locations,id',
            'lines.*.vw_id'          => 'required|exists:virtual_warehouses,id',
            'lines.*.qty'            => 'required|numeric|gt:0',
            'lines.*.uom_id'         => 'required|exists:units_of_measure,id',
            'lines.*.stock_status'   => 'required|in:available,on_hold',
        ]);
        $this->previewing = true;
    }

    public function post(StockLedger $ledger): void
    {
        $this->authorize('stock.opening-balance');

        $stockLines = collect($this->lines)->map(function ($l) {
            $serials = [];
            if (! empty($l['serials'])) {
                $serials = array_values(array_filter(
                    array_map('trim', explode("\n", str_replace("\r", '', $l['serials'])))
                ));
            }

            return new StockLine(
                itemId: (int) $l['item_id'],
                locationId: (int) $l['location_id'],
                virtualWarehouseId: (int) $l['vw_id'],
                stockStatus: StockStatus::from($l['stock_status']),
                enteredQty: (string) $l['qty'],
                enteredUomId: (int) $l['uom_id'],
                batchNo: $l['batch_no'] ?: null,
                expiryDate: $l['expiry_date'] ? Carbon::parse($l['expiry_date']) : null,
                serials: $serials,
            );
        })->all();

        try {
            $tx = $ledger->openingBalance($stockLines, $this->notes ?: null);
            $this->dispatch('toast', message: "Opening balance posted: {$tx->number}", type: 'success');
            $this->lines = [];
            $this->notes = '';
            $this->previewing = false;
            $this->addLine();
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function cancelPreview(): void
    {
        $this->previewing = false;
    }

    #[Computed]
    public function items()
    {
        return Item::active()->with('baseUom')->orderBy('name')->get();
    }

    #[Computed]
    public function locations()
    {
        return Location::active()->bin()->orderBy('code')->get();
    }

    #[Computed]
    public function virtualWarehouses()
    {
        return VirtualWarehouse::active()->orderBy('name')->get();
    }

    #[Computed]
    public function uoms()
    {
        return UnitOfMeasure::orderBy('code')->get();
    }

    public function render()
    {
        return view('livewire.inventory.stock.opening-balance')
            ->layout('components.layouts.app', [
                'title'           => 'Opening Balance',
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
