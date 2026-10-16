<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\Adjustments;

use App\Enums\ReasonCodeAppliesTo;
use App\Models\Inventory\Batch;
use App\Models\Item;
use App\Models\Location;
use App\Models\ReasonCode;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Services\Inventory\AdjustmentWorkflow;
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
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'item_id'        => '',
            'location_id'    => '',
            'batch_no'       => '',
            'reason_code_id' => '',
            'qty'            => '',
            'uom_id'         => '',
            'serials'        => '',
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

    public function save(AdjustmentWorkflow $workflow): void
    {
        $this->validate([
            'virtualWarehouseId'      => 'required|integer|exists:virtual_warehouses,id',
            'lines'                   => 'required|array|min:1',
            'lines.*.item_id'         => 'required|integer|exists:items,id',
            'lines.*.location_id'     => 'required|integer|exists:locations,id',
            'lines.*.batch_no'        => 'nullable|string|max:100',
            'lines.*.reason_code_id'  => 'required|integer|exists:reason_codes,id',
            'lines.*.qty'             => 'required|numeric|not_in:0',
            'lines.*.uom_id'          => 'required|integer|exists:units_of_measure,id',
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
                'item_id'        => (int) $l['item_id'],
                'location_id'    => (int) $l['location_id'],
                'batch_id'       => $batchId,
                'reason_code_id' => (int) $l['reason_code_id'],
                'qty'            => $l['qty'],
                'uom_id'         => (int) $l['uom_id'],
                'serials'        => $l['serials']
                    ? array_filter(array_map('trim', explode(',', $l['serials'])))
                    : null,
            ];
        }

        $data = [
            'virtual_warehouse_id' => $this->virtualWarehouseId,
            'notes'                => $this->notes ?: null,
            'lines'                => $resolvedLines,
        ];

        try {
            $adj = $workflow->create($data, auth()->user());
            $this->dispatch('toast', message: "Adjustment {$adj->number} created", type: 'success');
            $this->redirect(route('inventory.adjustments.show', $adj), navigate: true);
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        $warehouses  = VirtualWarehouse::all();
        $reasonCodes = ReasonCode::active()->where('applies_to', ReasonCodeAppliesTo::Adjustment)->get();

        return view('livewire.inventory.adjustments.create', compact('warehouses', 'reasonCodes'))
            ->layout('components.layouts.app', [
                'title'           => 'New Stock Adjustment',
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
