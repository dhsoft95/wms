<?php

declare(strict_types=1);

namespace App\Livewire\Warehouse\Movements;

use App\Data\Inventory\StockKey;
use App\Enums\Inventory\StockStatus;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
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

class Create extends Component
{
    public int    $virtualWarehouseId = 0;
    public string $notes              = '';
    public array  $lines              = [];

    public function mount(): void
    {
        $this->authorize('create', \App\Models\InternalMovement::class);
        $this->addLine();
    }

    #[Computed]
    public function virtualWarehouses()
    {
        return VirtualWarehouse::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function items()
    {
        return Item::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function binLocations()
    {
        return Location::active()
            ->bin()
            ->where('type', '!=', LocationType::Quarantine->value)
            ->orderBy('code')
            ->get();
    }

    #[Computed]
    public function uoms()
    {
        return UnitOfMeasure::orderBy('code')->get();
    }

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
    }

    public function removeLine(int $index): void
    {
        array_splice($this->lines, $index, 1);
        if (empty($this->lines)) {
            $this->addLine();
        }
    }

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

        // Cross-field validation
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

            // Available qty pre-check
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
                    itemId: $item->id,
                    batchId: $batchId,
                    lpnId: null,
                    locationId: (int) $line['from_location_id'],
                    virtualWarehouseId: $this->virtualWarehouseId,
                    stockStatus: StockStatus::Available,
                );

                $balance   = StockBalance::where('key_hash', $key->hash())->first();
                $available = $balance ? $balance->qtyAvailable() : '0.0000';

                if (bccomp($available, (string) $line['qty'], 4) < 0) {
                    $this->addError(
                        "lines.{$i}.qty",
                        "Insufficient stock. Available: {$available}."
                    );
                    return;
                }

                // Serial-tracked: serials required and count must match qty
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

        // Parse serials into arrays before passing to workflow
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
