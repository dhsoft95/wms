<?php

declare(strict_types=1);

namespace App\Livewire\Warehouse\Transfers;

use App\Livewire\Concerns\WithBulkLineUpload;
use App\Models\Inventory\Batch;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Services\Inventory\TransferWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads, WithBulkLineUpload;
    public int    $fromVwId = 0;
    public int    $toVwId   = 0;
    public string $notes    = '';
    public array  $lines    = [];

    public static function lineFields(): array
    {
        return [
            ['key' => 'item_id',     'column' => 'Item (SKU)', 'type' => 'sku',     'required' => true,  'format' => 'Item SKU code',     'example' => 'SKU-001'],
            ['key' => 'location_id', 'column' => 'Bin',        'type' => 'bin',     'required' => true,  'format' => 'Bin location code', 'example' => 'BIN-A01'],
            ['key' => 'uom_id',      'column' => 'Unit',       'type' => 'uom',     'required' => true,  'format' => 'UoM code',          'example' => 'EA'],
            ['key' => 'qty',         'column' => 'Quantity',   'type' => 'decimal', 'required' => true,  'format' => 'Number > 0',        'example' => '20'],
            ['key' => 'batch_no',    'column' => 'Batch No',   'type' => 'text',    'required' => false, 'format' => 'Text, max 100',     'example' => ''],
        ];
    }

    protected function bulkContext(): array
    {
        return [
            'virtual_warehouse_id' => $this->fromVwId,
            'check_available'      => true,
            'location_key'         => 'location_id',
        ];
    }

    protected function applyBulkLines(array $rows): void
    {
        if ($this->blkMode === 'replace') {
            $this->lines = [];
        }

        foreach ($rows as $row) {
            $this->lines[] = [
                'item_id'     => $row['item_id']     ?? '',
                'location_id' => $row['location_id'] ?? '',
                'batch_no'    => $row['batch_no']    ?? '',
                'qty'         => $row['qty']         ?? '',
                'uom_id'      => $row['uom_id']      ?? '',
            ];
        }

        if (empty($this->lines)) {
            $this->addLine();
        }
    }

    public function mount(): void
    {
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'item_id'     => '',
            'location_id' => '',
            'batch_no'    => '',
            'qty'         => '',
            'uom_id'      => '',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    #[Computed]
    public function items()
    {
        return Item::active()->orderBy('name')->get();
    }

    #[Computed]
    public function locations()
    {
        return Location::active()->bin()->orderBy('code')->get();
    }

    #[Computed]
    public function uoms()
    {
        return UnitOfMeasure::orderBy('code')->get();
    }

    public function save(TransferWorkflow $workflow): void
    {
        $this->authorize('create', \App\Models\StockTransfer::class);

        $this->validate([
            'fromVwId'            => 'required|integer|exists:virtual_warehouses,id',
            'toVwId'              => 'required|integer|exists:virtual_warehouses,id|different:fromVwId',
            'lines'               => 'required|array|min:1',
            'lines.*.item_id'     => 'required|integer|exists:items,id',
            'lines.*.location_id' => 'required|integer|exists:locations,id',
            'lines.*.batch_no'    => 'nullable|string|max:100',
            'lines.*.qty'         => 'required|numeric|min:0.0001',
            'lines.*.uom_id'      => 'required|integer|exists:units_of_measure,id',
        ]);

        // Resolve batch_no → batch_id
        $resolvedLines = [];
        foreach ($this->lines as $i => $l) {
            $batchId = null;
            if (! empty($l['batch_no'])) {
                $batch = Batch::where('item_id', (int) $l['item_id'])
                    ->where('batch_no', trim($l['batch_no']))
                    ->first();
                if (! $batch) {
                    $this->addError("lines.{$i}.batch_no", "Batch '{$l['batch_no']}' not found for this item.");
                    return;
                }
                $batchId = $batch->id;
            }

            $resolvedLines[] = [
                'item_id'     => (int) $l['item_id'],
                'location_id' => (int) $l['location_id'],
                'batch_id'    => $batchId,
                'qty'         => $l['qty'],
                'uom_id'      => (int) $l['uom_id'],
            ];
        }

        $data = [
            'from_virtual_warehouse_id' => $this->fromVwId,
            'to_virtual_warehouse_id'   => $this->toVwId,
            'notes'                     => $this->notes ?: null,
            'lines'                     => $resolvedLines,
            'department_id'             => auth()->user()->department_id,
        ];

        try {
            $transfer = $workflow->create($data, auth()->user());
            $this->dispatch('toast', message: "Transfer {$transfer->number} created", type: 'success');
            $this->redirect(route('warehouse.transfers.show', $transfer), navigate: true);
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        $warehouses = VirtualWarehouse::all();

        return view('livewire.warehouse.transfers.create', compact('warehouses'))
            ->layout('components.layouts.app', [
                'title'           => 'New Transfer',
                'breadcrumbGroup' => 'Warehouse Operations',
            ]);
    }
}
