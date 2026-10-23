<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\StockStatus;

use App\Enums\Inventory\SerialStatus;
use App\Enums\Inventory\StockStatusRequestType;
use App\Models\Department;
use App\Models\Inventory\SerialNumber;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\Location;
use App\Models\VirtualWarehouse;
use App\Services\Inventory\StockStatusRequestWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Create extends Component
{
    public int    $virtualWarehouseId = 0;
    public int    $departmentId       = 0;
    public string $type               = '';
    public string $reason             = '';
    public array  $lines              = [];

    public function mount(?string $type = null, ?int $balance_id = null): void
    {
        $this->type = $type ?? StockStatusRequestType::MarkObsolete->value;

        if ($balance_id) {
            $balance = \App\Models\Inventory\StockBalance::with('item', 'batch')->find($balance_id);
            if ($balance) {
                $this->virtualWarehouseId = $balance->virtual_warehouse_id;
                $this->departmentId       = $this->departmentForVw($this->virtualWarehouseId);
                $this->lines = [[
                    'item_id'     => (string) $balance->item_id,
                    'location_id' => (string) $balance->location_id,
                    'batch_id'    => $balance->batch_id ? (string) $balance->batch_id : '',
                    'qty'         => '',
                    'serial_ids'  => [],
                ]];
                return;
            }
        }

        // Default department from the logged-in user
        $this->departmentId = auth()->user()?->department_id ?? 0;
        $this->addLine();
    }

    public function updatedVirtualWarehouseId(): void
    {
        $this->departmentId = $this->departmentForVw($this->virtualWarehouseId);

        // Reset location on all lines — they may not be valid for the new VW
        foreach ($this->lines as $i => $_) {
            $this->lines[$i]['location_id'] = '';
        }

        unset($this->locations);
    }

    public function updatedType(): void
    {
        // Type change affects which stock status is shown in locations
        foreach ($this->lines as $i => $_) {
            $this->lines[$i]['location_id'] = '';
        }

        unset($this->locations);
    }

    private function departmentForVw(int $vwId): int
    {
        if ($vwId <= 0) {
            return auth()->user()?->department_id ?? 0;
        }

        return VirtualWarehouse::find($vwId)?->department_id
            ?? auth()->user()?->department_id
            ?? 0;
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'item_id'     => '',
            'location_id' => '',
            'batch_id'    => '',
            'qty'         => '',
            'serial_ids'  => [],
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function toggleLineSerial(int $lineIndex, int $serialId): void
    {
        $ids = $this->lines[$lineIndex]['serial_ids'] ?? [];
        $pos = array_search($serialId, $ids, true);

        if ($pos !== false) {
            array_splice($ids, $pos, 1);
        } else {
            $ids[] = $serialId;
        }

        $this->lines[$lineIndex]['serial_ids'] = array_values($ids);
    }

    #[Computed]
    public function warehouses()
    {
        return VirtualWarehouse::all();
    }

    #[Computed]
    public function departments()
    {
        return Department::orderBy('name')->get();
    }

    #[Computed]
    public function types(): array
    {
        return StockStatusRequestType::cases();
    }

    #[Computed]
    public function items()
    {
        return Item::active()->orderBy('name')->get();
    }

    #[Computed]
    public function serialTrackingMap(): array
    {
        return Item::active()->pluck('track_serial', 'id')->map(fn ($v) => (bool) $v)->all();
    }

    #[Computed]
    public function availableSerialsPerLine(): array
    {
        if (! $this->type || $this->virtualWarehouseId <= 0) {
            return [];
        }

        // Serial IDs already reserved by other Submitted SSRs for this VW + type
        $reservedIds = \App\Models\StockStatusRequestLineSerial::whereHas(
            'stockStatusRequestLine.stockStatusRequest',
            fn ($q) => $q
                ->where('virtual_warehouse_id', $this->virtualWarehouseId)
                ->where('type', $this->type)
                ->where('status', \App\Enums\Inventory\StockStatusRequestStatus::Submitted->value)
        )->pluck('serial_number_id')->all();

        $result = [];

        foreach ($this->lines as $i => $line) {
            $itemId     = (int) ($line['item_id'] ?? 0);
            $locationId = (int) ($line['location_id'] ?? 0);

            if (! $itemId || ! $locationId) {
                $result[$i] = [];
                continue;
            }

            $item = Item::find($itemId);
            if (! $item || ! $item->track_serial) {
                $result[$i] = [];
                continue;
            }

            $batchId = ! empty($line['batch_id']) ? (int) $line['batch_id'] : null;

            $result[$i] = SerialNumber::where('item_id', $itemId)
                ->where('location_id', $locationId)
                ->where('status', SerialStatus::InStock)
                ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
                ->when($reservedIds, fn ($q) => $q->whereNotIn('id', $reservedIds))
                ->orderBy('serial_no')
                ->get(['id', 'serial_no'])
                ->toArray();
        }

        return $result;
    }

    #[Computed]
    public function locations()
    {
        if ($this->virtualWarehouseId <= 0 || $this->type === '') {
            return collect();
        }

        $fromStatus = StockStatusRequestType::from($this->type)->fromStatus();

        // Only show bins that have stock under the selected VW with the correct status
        $locationIds = StockBalance::where('virtual_warehouse_id', $this->virtualWarehouseId)
            ->where('stock_status', $fromStatus)
            ->where('qty_on_hand', '>', 0)
            ->pluck('location_id')
            ->unique();

        return Location::active()->bin()
            ->whereIn('id', $locationIds)
            ->orderBy('code')
            ->get();
    }

    public function save(StockStatusRequestWorkflow $workflow): void
    {
        // Pre-fill qty for serial-tracked lines from selected serial count
        // so the generic 'required|numeric|gt:0' rule passes for them.
        foreach ($this->lines as $i => $l) {
            $itemId    = (int) ($l['item_id'] ?? 0);
            $serialIds = array_values(array_filter(array_map('intval', $l['serial_ids'] ?? [])));
            $isSerial  = $itemId > 0 && ($this->serialTrackingMap[$itemId] ?? false);

            if ($isSerial) {
                if (empty($serialIds)) {
                    $this->addError("lines.{$i}.serial_ids", 'Select at least one serial number.');
                }
                $this->lines[$i]['qty'] = (string) count($serialIds);
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->validate([
            'virtualWarehouseId'    => 'required|integer|exists:virtual_warehouses,id',
            'departmentId'          => 'required|integer|exists:departments,id',
            'type'                  => 'required|string|in:mark_obsolete,restore',
            'reason'                => 'required|string|min:5',
            'lines'                 => 'required|array|min:1',
            'lines.*.item_id'       => 'required|integer|exists:items,id',
            'lines.*.location_id'   => 'required|integer|exists:locations,id',
            'lines.*.batch_id'      => 'nullable|integer|exists:batches,id',
            'lines.*.qty'           => 'required|numeric|gt:0',
        ]);

        $resolvedLines = [];
        foreach ($this->lines as $l) {
            $serialIds = array_values(array_filter(array_map('intval', $l['serial_ids'] ?? [])));

            $resolvedLines[] = [
                'item_id'           => (int) $l['item_id'],
                'location_id'       => (int) $l['location_id'],
                'batch_id'          => ! empty($l['batch_id']) ? (int) $l['batch_id'] : null,
                'qty'               => $l['qty'],
                'serial_number_ids' => $serialIds,
            ];
        }

        try {
            $req = $workflow->create([
                'virtual_warehouse_id' => $this->virtualWarehouseId,
                'department_id'        => $this->departmentId,
                'type'                 => $this->type,
                'reason'               => $this->reason,
                'lines'                => $resolvedLines,
            ], auth()->user());

            $req = $workflow->submit($req, auth()->user());

            $this->dispatch('toast', message: "SSR {$req->number} submitted for approval.", type: 'success');
            $this->redirect(route('inventory.stock-status.show', $req), navigate: true);
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        return view('livewire.inventory.stock-status.create')
            ->layout('components.layouts.app', [
                'title'           => 'Stock Status Request',
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
