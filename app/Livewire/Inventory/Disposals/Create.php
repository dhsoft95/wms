<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\Disposals;

use App\Enums\Inventory\DisposalMethod;
use App\Enums\Inventory\StockStatus;
use App\Models\Inventory\Batch;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Location;
use App\Models\VirtualWarehouse;
use App\Services\Inventory\DisposalWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Create extends Component
{
    public int    $virtualWarehouseId = 0;
    public string $method             = '';
    public string $reason             = '';
    public array  $lines              = [];

    public function mount(): void
    {
        $this->method = DisposalMethod::Destroy->value;
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'item_id'      => '',
            'location_id'  => '',
            'batch_id'     => '',
            'stock_status' => StockStatus::Obsolete->value,
            'qty'          => '',
            'value'        => null,
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function updatedLines(mixed $value, string $path): void
    {
        // Auto-compute value when qty or batch changes
        [$index, $field] = explode('.', $path, 2) + ['', ''];
        if (in_array($field, ['qty', 'batch_id'], true)) {
            $this->computeLineValue((int) $index);
        }
    }

    private function computeLineValue(int $index): void
    {
        $line = $this->lines[$index] ?? null;
        if ($line === null) {
            return;
        }

        $batchId  = (int) ($line['batch_id'] ?? 0);
        $qty      = (string) ($line['qty'] ?? '0');

        if (! $batchId || bccomp($qty, '0', 4) <= 0) {
            $this->lines[$index]['value'] = null;
            return;
        }

        $unitPrice = \Illuminate\Support\Facades\DB::table('grn_lines')
            ->join('grn_receipts', 'grn_receipts.grn_line_id', '=', 'grn_lines.id')
            ->where('grn_receipts.batch_id', $batchId)
            ->whereNotNull('grn_lines.unit_price')
            ->orderBy('grn_receipts.id')
            ->value('grn_lines.unit_price');

        $this->lines[$index]['value'] = $unitPrice
            ? bcmul($qty, (string) $unitPrice, 4)
            : null;
    }

    #[Computed]
    public function warehouses()
    {
        return VirtualWarehouse::all();
    }

    #[Computed]
    public function methods(): array
    {
        return DisposalMethod::cases();
    }

    #[Computed]
    public function items()
    {
        return Item::active()->orderBy('name')->get();
    }

    #[Computed]
    public function locations()
    {
        return Location::active()->bin()
            ->whereNotIn('type', ['disposal', 'receiving_dock', 'yard_slot', 'transit'])
            ->orderBy('code')
            ->get();
    }

    public function batches(int $itemId): \Illuminate\Support\Collection
    {
        return Batch::where('item_id', $itemId)->orderBy('batch_no')->get();
    }

    public function save(DisposalWorkflow $workflow): void
    {
        $this->validate([
            'virtualWarehouseId'        => 'required|integer|exists:virtual_warehouses,id',
            'method'                    => 'required|string|in:auction,destroy',
            'reason'                    => 'required|string|min:5',
            'lines'                     => 'required|array|min:1',
            'lines.*.item_id'           => 'required|integer|exists:items,id',
            'lines.*.location_id'       => 'required|integer|exists:locations,id',
            'lines.*.batch_id'          => 'nullable|integer|exists:batches,id',
            'lines.*.stock_status'      => 'required|string|in:obsolete,on_hold,available',
            'lines.*.qty'               => 'required|numeric|gt:0',
        ]);

        $resolvedLines = [];
        foreach ($this->lines as $l) {
            $resolvedLines[] = [
                'item_id'      => (int) $l['item_id'],
                'location_id'  => (int) $l['location_id'],
                'batch_id'     => ! empty($l['batch_id']) ? (int) $l['batch_id'] : null,
                'stock_status' => StockStatus::from($l['stock_status']),
                'qty'          => $l['qty'],
            ];
        }

        try {
            $req = $workflow->create([
                'virtual_warehouse_id' => $this->virtualWarehouseId,
                'method'               => $this->method,
                'reason'               => $this->reason,
                'lines'                => $resolvedLines,
            ], auth()->user());

            $req = $workflow->submit($req, auth()->user());

            $this->dispatch('toast', message: "Disposal {$req->number} submitted for approval.", type: 'success');
            $this->redirect(route('inventory.disposals.show', $req), navigate: true);
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        return view('livewire.inventory.disposals.create')
            ->layout('components.layouts.app', [
                'title'           => 'New Disposal Request',
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
