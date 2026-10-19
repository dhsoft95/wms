<?php

declare(strict_types=1);

namespace App\Livewire\Warehouse\Movements;

use App\Data\Inventory\StockKey;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Livewire\Concerns\WithBulkLineUpload;
use App\Models\Inventory\Batch;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Services\Warehouse\MovementWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads, WithBulkLineUpload;

    public int    $virtualWarehouseId = 0;
    public string $notes              = '';
    public array  $lines              = [];

    public static function lineFields(): array
    {
        return [
            ['key' => 'item_id',          'column' => 'Item (SKU)',  'type' => 'sku',     'required' => true,  'format' => 'Item SKU code',     'example' => 'SKU-001'],
            ['key' => 'from_location_id', 'column' => 'From Bin',    'type' => 'bin',     'required' => true,  'format' => 'Bin location code', 'example' => 'BIN-A01'],
            ['key' => 'to_location_id',   'column' => 'To Bin',      'type' => 'bin',     'required' => true,  'format' => 'Bin location code', 'example' => 'BIN-B02'],
            ['key' => 'uom_id',           'column' => 'Unit',        'type' => 'uom',     'required' => true,  'format' => 'UoM code',          'example' => 'EA'],
            ['key' => 'qty',              'column' => 'Quantity',    'type' => 'decimal', 'required' => true,  'format' => 'Number > 0',        'example' => '50'],
            ['key' => 'batch_no',         'column' => 'Batch No',    'type' => 'text',    'required' => false, 'format' => 'Text, max 100',     'example' => ''],
            ['key' => 'expiry_date',      'column' => 'Expiry Date', 'type' => 'date',    'required' => false, 'format' => 'YYYY-MM-DD',        'example' => ''],
            ['key' => 'serials',          'column' => 'Serials',     'type' => 'serials', 'required' => false, 'format' => 'Comma-separated SNs', 'example' => ''],
        ];
    }

    protected function bulkContext(): array
    {
        return [
            'virtual_warehouse_id' => $this->virtualWarehouseId,
            'check_available'      => true,
            'location_key'         => 'from_location_id',
        ];
    }

    protected function applyBulkLines(array $rows): void
    {
        if ($this->blkMode === 'replace') {
            $this->lines = [];
        }

        foreach ($rows as $row) {
            $this->lines[] = [
                'item_id'          => $row['item_id']          ?? '',
                'from_location_id' => $row['from_location_id'] ?? '',
                'to_location_id'   => $row['to_location_id']   ?? '',
                'batch_no'         => $row['batch_no']         ?? '',
                'expiry_date'      => $row['expiry_date']      ?? '',
                'qty'              => $row['qty']              ?? '',
                'uom_id'           => $row['uom_id']           ?? '',
                'serials'          => is_array($row['serials'] ?? null)
                    ? implode(', ', $row['serials'])
                    : ($row['serials'] ?? ''),
            ];
        }

        if (empty($this->lines)) {
            $this->addLine();
        }
    }

    public function mount(): void
    {
        $this->authorize('create', \App\Models\InternalMovement::class);
        $this->addLine();
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function updated(string $property, mixed $value): void
    {
        // From Bin changed → reset item, clear caches
        if (preg_match('/^lines\.(\d+)\.from_location_id$/', $property, $m)) {
            $i = (int) $m[1];
            $this->lines[$i]['item_id'] = '';
            $this->lines[$i]['uom_id']  = '';
            $this->lines[$i]['qty']     = '';
            unset($this->stockByLine, $this->availableByLine);
        }

        // Item changed → auto-fill VW + UoM from stock at selected bin
        if (preg_match('/^lines\.(\d+)\.item_id$/', $property, $m)) {
            $i = (int) $m[1];
            $this->autoFillFromStock($i);
            unset($this->availableByLine);
        }
    }

    private function autoFillFromStock(int $i): void
    {
        $locationId = (int) ($this->lines[$i]['from_location_id'] ?? 0);
        $itemId     = (int) ($this->lines[$i]['item_id'] ?? 0);

        if (! $locationId || ! $itemId) {
            return;
        }

        // Find the balance at this exact location+item
        $balance = StockBalance::where('location_id', $locationId)
            ->where('item_id', $itemId)
            ->where('stock_status', StockStatus::Available->value)
            ->orderByDesc('qty_on_hand')
            ->first();

        if (! $balance) {
            return;
        }

        // Auto-fill VW if not yet set
        if (! $this->virtualWarehouseId && $balance->virtual_warehouse_id) {
            $this->virtualWarehouseId = $balance->virtual_warehouse_id;
        }

        // Auto-fill UoM from item's base UoM
        if (empty($this->lines[$i]['uom_id'])) {
            $item = Item::find($itemId);
            if ($item?->base_uom_id) {
                $this->lines[$i]['uom_id'] = $item->base_uom_id;
            }
        }
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function virtualWarehouses()
    {
        return VirtualWarehouse::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function binLocations()
    {
        return Location::active()
            ->bin()
            ->where('type', '!=', LocationType::Quarantine->value)
            ->orderBy('code')
            ->get(['id', 'code']);
    }

    #[Computed]
    public function uoms()
    {
        return UnitOfMeasure::orderBy('code')->get(['id', 'code', 'name']);
    }

    /**
     * For each line, returns the list of items with stock at the selected from bin.
     * Shape: [ lineIndex => [ ['item' => Item, 'qty' => string, 'vw_id' => int, 'vw_name' => string], ... ] ]
     */
    #[Computed]
    public function stockByLine(): array
    {
        $result = [];

        foreach ($this->lines as $i => $line) {
            $locationId = (int) ($line['from_location_id'] ?? 0);

            if (! $locationId) {
                $result[$i] = [];
                continue;
            }

            $rows = StockBalance::with(['item', 'virtualWarehouse'])
                ->where('location_id', $locationId)
                ->where('stock_status', StockStatus::Available->value)
                ->where('qty_on_hand', '>', 0)
                ->get()
                ->groupBy('item_id')
                ->map(function ($group) {
                    $first = $group->first();
                    return [
                        'item'    => $first->item,
                        'qty'     => $group->sum('qty_on_hand'),
                        'vw_id'   => $first->virtual_warehouse_id,
                        'vw_name' => $first->virtualWarehouse?->name ?? '—',
                    ];
                })
                ->values()
                ->all();

            $result[$i] = $rows;
        }

        return $result;
    }

    /**
     * For each line, returns the available qty string for the selected item+bin+vw combination.
     * Shape: [ lineIndex => '10.0000' | null ]
     */
    #[Computed]
    public function availableByLine(): array
    {
        $result = [];

        foreach ($this->lines as $i => $line) {
            $locationId = (int) ($line['from_location_id'] ?? 0);
            $itemId     = (int) ($line['item_id'] ?? 0);
            $vwId       = (int) $this->virtualWarehouseId;

            if (! $locationId || ! $itemId || ! $vwId) {
                $result[$i] = null;
                continue;
            }

            $key = new StockKey(
                itemId:             $itemId,
                batchId:            null,
                lpnId:              null,
                locationId:         $locationId,
                virtualWarehouseId: $vwId,
                stockStatus:        StockStatus::Available,
            );

            $balance    = StockBalance::where('key_hash', $key->hash())->first();
            $result[$i] = $balance ? $balance->qtyAvailable() : '0.0000';
        }

        return $result;
    }

    // ─── Line Management ──────────────────────────────────────────────────────

    public function addLine(): void
    {
        $this->lines[] = [
            'item_id'          => '',
            'from_location_id' => '',
            'to_location_id'   => '',
            'batch_no'         => '',
            'expiry_date'      => '',
            'qty'              => '',
            'uom_id'           => '',
            'serials'          => '',
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

    // ─── Save ─────────────────────────────────────────────────────────────────

    public function save(MovementWorkflow $workflow): void
    {
        $this->authorize('create', \App\Models\InternalMovement::class);

        $this->validate([
            'virtualWarehouseId'          => 'required|integer|exists:virtual_warehouses,id',
            'lines'                        => 'required|array|min:1',
            'lines.*.item_id'              => 'required|integer|exists:items,id',
            'lines.*.from_location_id'     => 'required|integer|exists:locations,id',
            'lines.*.to_location_id'       => 'required|integer|exists:locations,id',
            'lines.*.batch_no'             => 'nullable|string|max:100',
            'lines.*.expiry_date'          => 'nullable|date',
            'lines.*.qty'                  => 'required|numeric|min:0.0001',
            'lines.*.uom_id'               => 'required|integer|exists:units_of_measure,id',
            'lines.*.serials'              => 'nullable|string',
        ]);

        foreach ($this->lines as $i => $line) {
            if ((int) $line['from_location_id'] === (int) $line['to_location_id']) {
                $this->addError("lines.{$i}.to_location_id", 'Destination must differ from source.');
                return;
            }

            $from = Location::find($line['from_location_id']);
            $to   = Location::find($line['to_location_id']);

            if ($from && (! $from->isBin() || $from->status !== LocationStatus::Active || $from->type === LocationType::Quarantine)) {
                $this->addError("lines.{$i}.from_location_id", 'Source must be an active non-quarantine bin.');
                return;
            }

            if ($to && (! $to->isBin() || $to->status !== LocationStatus::Active || $to->type === LocationType::Quarantine)) {
                $this->addError("lines.{$i}.to_location_id", 'Destination must be an active non-quarantine bin.');
                return;
            }

            $item = Item::find($line['item_id']);
            if ($item) {
                $batchId = null;
                if (! empty($line['batch_no'])) {
                    $batch = Batch::where('item_id', $item->id)->where('batch_no', trim($line['batch_no']))->first();
                    if (! $batch) {
                        $this->addError("lines.{$i}.batch_no", "Batch '{$line['batch_no']}' not found for this item.");
                        return;
                    }
                    $batchId = $batch->id;
                }

                $key = new StockKey(
                    itemId:             $item->id,
                    batchId:            $batchId,
                    lpnId:              null,
                    locationId:         (int) $line['from_location_id'],
                    virtualWarehouseId: $this->virtualWarehouseId,
                    stockStatus:        StockStatus::Available,
                );

                $balance   = StockBalance::where('key_hash', $key->hash())->first();
                $available = $balance ? $balance->qtyAvailable() : '0.0000';

                if (bccomp($available, (string) $line['qty'], 4) < 0) {
                    $this->addError("lines.{$i}.qty", "Insufficient stock. Available: {$available}.");
                    return;
                }

                if ($item->track_serial) {
                    $serials = $this->parseSerials($line['serials'] ?? '');
                    $absQty  = (int) round((float) $line['qty']);
                    if (empty($serials)) {
                        $this->addError("lines.{$i}.serials", "Serial numbers are required for this item ({$absQty} needed).");
                        return;
                    }
                    if (count($serials) !== $absQty) {
                        $this->addError("lines.{$i}.serials", "Expected {$absQty} serial number(s), got " . count($serials) . '.');
                        return;
                    }
                }
            }
        }

        $lines = collect($this->lines)->map(function ($line) {
            $line['serials'] = $this->parseSerials($line['serials'] ?? '');
            return $line;
        })->all();

        try {
            $movement = $workflow->create([
                'virtual_warehouse_id' => $this->virtualWarehouseId,
                'notes'                => $this->notes ?: null,
                'lines'                => $lines,
            ], auth()->user());

            $this->dispatch('toast', message: "Movement {$movement->number} created.", type: 'success');
            $this->redirect(route('warehouse.movements.show', $movement), navigate: true);
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    private function parseSerials(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/[\r\n,]+/', $raw)),
            fn ($s) => $s !== ''
        ));
    }

    public function render(): View
    {
        return view('livewire.warehouse.movements.create')
            ->layout('components.layouts.app', [
                'title'           => 'New Movement',
                'breadcrumbGroup' => 'Warehouse Operations',
            ]);
    }
}
