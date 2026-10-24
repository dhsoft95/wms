<?php

declare(strict_types=1);

namespace App\Livewire\Warehouse\Wtr;

use App\Data\Inventory\StockKey;
use App\Enums\Inventory\StockStatus;
use App\Livewire\Concerns\WithBulkLineUpload;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use App\Services\Transfers\WarehouseTransferWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads, WithBulkLineUpload;

    public int    $fromWarehouseId = 0;
    public int    $toWarehouseId   = 0;
    public int    $fromVwId        = 0;
    public int    $toVwId          = 0;
    public string $notes           = '';
    public array  $lines           = [];

    public static function lineFields(): array
    {
        return [
            ['key' => 'source_location_id', 'column' => 'Source Bin',  'type' => 'bin',     'required' => true,  'format' => 'Bin location code', 'example' => 'BIN-A01'],
            ['key' => 'item_id',            'column' => 'Item (SKU)',   'type' => 'sku',     'required' => true,  'format' => 'Item SKU code',     'example' => 'SKU-001'],
            ['key' => 'uom_id',             'column' => 'Unit',         'type' => 'uom',     'required' => true,  'format' => 'UoM code',          'example' => 'EA'],
            ['key' => 'qty',                'column' => 'Quantity',     'type' => 'decimal', 'required' => true,  'format' => 'Number > 0',        'example' => '30'],
            ['key' => 'batch_no',           'column' => 'Batch No',     'type' => 'text',    'required' => false, 'format' => 'Text, max 100',     'example' => ''],
            ['key' => 'expiry_date',        'column' => 'Expiry Date',  'type' => 'date',    'required' => false, 'format' => 'YYYY-MM-DD',        'example' => ''],
        ];
    }

    protected function bulkContext(): array
    {
        return [
            'virtual_warehouse_id' => $this->fromVwId,
            'check_available'      => true,
            'location_key'         => 'source_location_id',
        ];
    }

    protected function applyBulkLines(array $rows): void
    {
        if ($this->blkMode === 'replace') {
            $this->lines = [];
        }

        foreach ($rows as $row) {
            $this->lines[] = [
                'source_location_id' => $row['source_location_id'] ?? '',
                'item_id'            => $row['item_id']            ?? '',
                'uom_id'             => $row['uom_id']             ?? '',
                'qty'                => $row['qty']                ?? '',
                'batch_no'           => $row['batch_no']           ?? '',
                'expiry_date'        => $row['expiry_date']        ?? '',
            ];
        }

        if (empty($this->lines)) {
            $this->addLine();
        }
    }

    public function mount(): void
    {
        $this->authorize('create', \App\Models\WarehouseTransfer::class);
        $this->addLine();
    }

    // ─── Lifecycle ─────────────────────────────────────────────────────────────

    public function updated(string $property, mixed $value): void
    {
        // From warehouse changed → reset VW + all lines
        if ($property === 'fromWarehouseId') {
            $this->fromVwId = 0;
            foreach (array_keys($this->lines) as $i) {
                $this->lines[$i]['source_location_id'] = '';
                $this->lines[$i]['item_id']            = '';
                $this->lines[$i]['uom_id']             = '';
                $this->lines[$i]['qty']                = '';
            }
            unset($this->stockByLine, $this->availableByLine, $this->fromVirtualWarehouses, $this->fromBins);
            return;
        }

        // From VW changed → reset items per line (bins stay, belong to warehouse not VW)
        if ($property === 'fromVwId') {
            foreach (array_keys($this->lines) as $i) {
                $this->lines[$i]['item_id'] = '';
                $this->lines[$i]['uom_id']  = '';
                $this->lines[$i]['qty']     = '';
            }
            unset($this->stockByLine, $this->availableByLine);
            return;
        }

        // To warehouse changed → reset to VW
        if ($property === 'toWarehouseId') {
            $this->toVwId = 0;
            unset($this->toVirtualWarehouses);
            return;
        }

        // Source bin changed → reset item + downstream
        if (preg_match('/^lines\.(\d+)\.source_location_id$/', $property, $m)) {
            $i = (int) $m[1];
            $this->lines[$i]['item_id'] = '';
            $this->lines[$i]['uom_id']  = '';
            $this->lines[$i]['qty']     = '';
            unset($this->stockByLine, $this->availableByLine);
        }

        // Item changed → auto-fill UoM
        if (preg_match('/^lines\.(\d+)\.item_id$/', $property, $m)) {
            $i = (int) $m[1];
            $this->autoFillUom($i);
            unset($this->availableByLine);
        }
    }

    private function autoFillUom(int $i): void
    {
        $itemId = (int) ($this->lines[$i]['item_id'] ?? 0);
        if (! $itemId || ! empty($this->lines[$i]['uom_id'])) {
            return;
        }

        $item = Item::find($itemId);
        if ($item?->base_uom_id) {
            $this->lines[$i]['uom_id'] = $item->base_uom_id;
        }
    }

    // ─── Computed ──────────────────────────────────────────────────────────────

    #[Computed]
    public function warehouses()
    {
        return Warehouse::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function fromVirtualWarehouses()
    {
        if (! $this->fromWarehouseId) {
            return collect();
        }

        return VirtualWarehouse::where('warehouse_id', $this->fromWarehouseId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function toVirtualWarehouses()
    {
        if (! $this->toWarehouseId) {
            return collect();
        }

        return VirtualWarehouse::where('warehouse_id', $this->toWarehouseId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function fromBins()
    {
        if (! $this->fromWarehouseId) {
            return collect();
        }

        return Location::active()->bin()
            ->where('warehouse_id', $this->fromWarehouseId)
            ->orderBy('code')
            ->get(['id', 'code']);
    }

    #[Computed]
    public function uoms()
    {
        return UnitOfMeasure::orderBy('code')->get(['id', 'code']);
    }

    /**
     * Items with available stock at the selected source bin + fromVwId per line.
     * Shape: [ lineIndex => [ ['item' => Item, 'qty' => float], ... ] ]
     */
    #[Computed]
    public function stockByLine(): array
    {
        $result = [];

        foreach ($this->lines as $i => $line) {
            $locationId = (int) ($line['source_location_id'] ?? 0);

            if (! $locationId || ! $this->fromVwId) {
                $result[$i] = [];
                continue;
            }

            $rows = StockBalance::with('item')
                ->where('location_id', $locationId)
                ->where('virtual_warehouse_id', $this->fromVwId)
                ->where('stock_status', StockStatus::Available->value)
                ->where('qty_on_hand', '>', 0)
                ->get()
                ->groupBy('item_id')
                ->map(function ($group) {
                    $first = $group->first();

                    return [
                        'item' => $first->item,
                        'qty'  => $group->sum('qty_on_hand'),
                    ];
                })
                ->values()
                ->all();

            $result[$i] = $rows;
        }

        return $result;
    }

    /**
     * Available qty for selected item + source bin + fromVwId per line.
     * Shape: [ lineIndex => '10.0000' | null ]
     */
    #[Computed]
    public function availableByLine(): array
    {
        $result = [];

        foreach ($this->lines as $i => $line) {
            $locationId = (int) ($line['source_location_id'] ?? 0);
            $itemId     = (int) ($line['item_id'] ?? 0);

            if (! $locationId || ! $itemId || ! $this->fromVwId) {
                $result[$i] = null;
                continue;
            }

            $key = new StockKey(
                itemId:             $itemId,
                batchId:            null,
                lpnId:              null,
                locationId:         $locationId,
                virtualWarehouseId: $this->fromVwId,
                stockStatus:        StockStatus::Available,
            );

            $balance    = StockBalance::where('key_hash', $key->hash())->first();
            $result[$i] = $balance ? $balance->qtyAvailable() : '0.0000';
        }

        return $result;
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'source_location_id' => '',
            'item_id'            => '',
            'uom_id'             => '',
            'qty'                => '',
            'batch_no'           => '',
            'expiry_date'        => '',
        ];
        unset($this->stockByLine, $this->availableByLine);
    }

    public function removeLine(int $index): void
    {
        array_splice($this->lines, $index, 1);
        if (empty($this->lines)) {
            $this->addLine();
        }
        unset($this->stockByLine, $this->availableByLine);
    }

    public function save(WarehouseTransferWorkflow $workflow): void
    {
        $this->authorize('create', \App\Models\WarehouseTransfer::class);

        $this->validate([
            'fromWarehouseId'              => 'required|integer|exists:warehouses,id',
            'toWarehouseId'                => 'required|integer|exists:warehouses,id|different:fromWarehouseId',
            'fromVwId'                     => 'required|integer|exists:virtual_warehouses,id',
            'lines'                        => 'required|array|min:1',
            'lines.*.source_location_id'   => 'required|integer|exists:locations,id',
            'lines.*.item_id'              => 'required|integer|exists:items,id',
            'lines.*.uom_id'               => 'required|integer|exists:units_of_measure,id',
            'lines.*.qty'                  => 'required|numeric|min:0.0001',
            'lines.*.batch_no'             => 'nullable|string|max:100',
            'lines.*.expiry_date'          => 'nullable|date',
        ]);

        $data = [
            'from_warehouse_id'         => $this->fromWarehouseId,
            'to_warehouse_id'           => $this->toWarehouseId,
            'from_virtual_warehouse_id' => $this->fromVwId,
            'to_virtual_warehouse_id'   => $this->toVwId ?: null,
            'notes'                     => $this->notes ?: null,
            'lines'                     => collect($this->lines)->map(fn ($l) => [
                'source_location_id' => (int) $l['source_location_id'],
                'item_id'            => (int) $l['item_id'],
                'uom_id'             => (int) $l['uom_id'],
                'qty'                => $l['qty'],
                'qty_base'           => $l['qty'],
                'batch_no'           => $l['batch_no'] ?: null,
                'expiry_date'        => $l['expiry_date'] ?: null,
            ])->toArray(),
        ];

        try {
            $wtr = $workflow->create($data, auth()->user());
            $this->dispatch('toast', message: "Warehouse transfer {$wtr->number} created", type: 'success');
            $this->redirect(route('warehouse.wtr.show', $wtr), navigate: true);
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        return view('livewire.warehouse.wtr.create')
            ->layout('components.layouts.app', [
                'title'           => 'New Warehouse Transfer',
                'breadcrumbGroup' => 'Operations',
            ]);
    }
}
