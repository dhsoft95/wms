<?php

declare(strict_types=1);

namespace App\Livewire\Warehouse\Wtr;

use App\Livewire\Concerns\WithBulkLineUpload;
use App\Models\Item;
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
            ['key' => 'item_id',     'column' => 'Item (SKU)', 'type' => 'sku',     'required' => true,  'format' => 'Item SKU code', 'example' => 'SKU-001'],
            ['key' => 'uom_id',      'column' => 'Unit',       'type' => 'uom',     'required' => true,  'format' => 'UoM code',      'example' => 'EA'],
            ['key' => 'qty',         'column' => 'Quantity',   'type' => 'decimal', 'required' => true,  'format' => 'Number > 0',    'example' => '30'],
            ['key' => 'batch_no',    'column' => 'Batch No',   'type' => 'text',    'required' => false, 'format' => 'Text, max 100', 'example' => ''],
            ['key' => 'expiry_date', 'column' => 'Expiry Date','type' => 'date',    'required' => false, 'format' => 'YYYY-MM-DD',    'example' => ''],
        ];
    }

    protected function bulkContext(): array
    {
        return [];
    }

    protected function applyBulkLines(array $rows): void
    {
        if ($this->blkMode === 'replace') {
            $this->lines = [];
        }

        foreach ($rows as $row) {
            $this->lines[] = [
                'item_id'     => $row['item_id']     ?? '',
                'uom_id'      => $row['uom_id']      ?? '',
                'qty'         => $row['qty']         ?? '',
                'batch_no'    => $row['batch_no']    ?? '',
                'expiry_date' => $row['expiry_date'] ?? '',
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

    public function addLine(): void
    {
        $this->lines[] = [
            'item_id'     => '',
            'uom_id'      => '',
            'qty'         => '',
            'batch_no'    => '',
            'expiry_date' => '',
        ];
    }

    public function removeLine(int $index): void
    {
        array_splice($this->lines, $index, 1);
        if (empty($this->lines)) {
            $this->addLine();
        }
    }

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
    public function items()
    {
        return Item::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function uoms()
    {
        return UnitOfMeasure::orderBy('code')->get();
    }

    public function updatedFromWarehouseId(): void
    {
        $this->fromVwId = 0;
    }

    public function updatedToWarehouseId(): void
    {
        $this->toVwId = 0;
    }

    public function save(WarehouseTransferWorkflow $workflow): void
    {
        $this->authorize('create', \App\Models\WarehouseTransfer::class);

        $this->validate([
            'fromWarehouseId'        => 'required|integer|exists:warehouses,id',
            'toWarehouseId'          => 'required|integer|exists:warehouses,id|different:fromWarehouseId',
            'fromVwId'               => 'required|integer|exists:virtual_warehouses,id',
            'lines'                  => 'required|array|min:1',
            'lines.*.item_id'        => 'required|integer|exists:items,id',
            'lines.*.uom_id'         => 'required|integer|exists:units_of_measure,id',
            'lines.*.qty'            => 'required|numeric|min:0.0001',
            'lines.*.batch_no'       => 'nullable|string|max:100',
            'lines.*.expiry_date'    => 'nullable|date',
        ]);

        $data = [
            'from_warehouse_id'         => $this->fromWarehouseId,
            'to_warehouse_id'           => $this->toWarehouseId,
            'from_virtual_warehouse_id' => $this->fromVwId,
            'to_virtual_warehouse_id'   => $this->toVwId ?: null,
            'notes'                     => $this->notes ?: null,
            'lines'                     => collect($this->lines)->map(fn ($l) => [
                'item_id'     => (int) $l['item_id'],
                'uom_id'      => (int) $l['uom_id'],
                'qty'         => $l['qty'],
                'qty_base'    => $l['qty'],
                'batch_no'    => $l['batch_no'] ?: null,
                'expiry_date' => $l['expiry_date'] ?: null,
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
