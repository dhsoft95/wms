<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\Counts;

use App\Enums\CycleCountStatus;
use App\Enums\ScanResultType;
use App\Livewire\Concerns\HandlesScans;
use App\Models\CycleCount;
use App\Models\CycleCountLine;
use App\Models\Inventory\StockBalance;
use App\Services\Inventory\CycleCountWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    use HandlesScans;

    public CycleCount $count;
    // keyed by line id → counted_qty input (blind: system_qty never sent to frontend)
    public array   $entries            = [];
    public bool    $unitScanMode       = false;
    public ?int    $lastScannedLineId  = null;

    public function mount(): void
    {
        $this->count->load('lines.item', 'lines.location', 'lines.batch', 'lines.uom');
        foreach ($this->count->lines as $line) {
            $this->entries[$line->id] = $line->counted_qty !== null ? (string) $line->counted_qty : '';
        }
    }

    public function processScan(CycleCountWorkflow $workflow): void
    {
        $result = $this->resolveScan();
        if ($result === null) return;

        if (! $result->ok()) {
            $this->scanFail($result->error ?? 'Barcode not recognised.');
            return;
        }

        $lineId = match ($result->type) {
            ScanResultType::Item     => $this->findLineByItem($result->record?->getKey()),
            ScanResultType::Lpn      => $this->findLineByLpnItem($result->record?->getKey()),
            ScanResultType::Location => $this->findLineByLocation($result->record?->getKey()),
            default                  => null,
        };

        if ($lineId === null) {
            $this->scanFail('Not found on this count sheet.');
            return;
        }

        if ($this->unitScanMode && $this->lastScannedLineId === $lineId) {
            $this->entries[$lineId] = (string) ((float) ($this->entries[$lineId] ?? 0) + 1);
            $this->saveEntry($lineId, $workflow);
            $this->scanOk('+1 added.');
        } else {
            $this->lastScannedLineId = $lineId;
            $this->dispatch('focus-count-line', id: $lineId);
            $this->scanOk('Line found — enter quantity.');
        }
    }

    private function findLineByItem(?int $itemId): ?int
    {
        if ($itemId === null) return null;
        return $this->count->lines->first(fn ($l) => $l->item_id === $itemId)?->id;
    }

    private function findLineByLpnItem(?int $lpnId): ?int
    {
        if ($lpnId === null) return null;
        $itemId = StockBalance::where('lpn_id', $lpnId)->value('item_id');
        return $itemId ? $this->findLineByItem($itemId) : null;
    }

    private function findLineByLocation(?int $locationId): ?int
    {
        if ($locationId === null) return null;
        return $this->count->lines->first(fn ($l) => $l->location_id === $locationId)?->id;
    }

    public function saveEntry(int $lineId, CycleCountWorkflow $workflow): void
    {
        $this->authorize('recordCount', $this->count);

        $qty = $this->entries[$lineId] ?? '';
        if (! is_numeric($qty) || (float) $qty < 0) {
            $this->dispatch('toast', message: 'Enter a valid non-negative quantity', type: 'error');
            return;
        }

        try {
            $line = CycleCountLine::findOrFail($lineId);
            $workflow->recordCount($line, $qty, auth()->user());
            $this->count->refresh();
            $this->dispatch('toast', message: 'Entry saved', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function submitForReview(CycleCountWorkflow $workflow): void
    {
        $this->authorize('recordCount', $this->count);

        try {
            $this->count = $workflow->submitForReview($this->count, auth()->user());
            $this->dispatch('toast', message: 'Count submitted for variance review', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function post(CycleCountWorkflow $workflow): void
    {
        $this->authorize('approve', $this->count);

        try {
            $this->count = $workflow->post($this->count, auth()->user());
            $this->dispatch('toast', message: "Count {$this->count->number} posted — variances adjusted", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function cancel(CycleCountWorkflow $workflow): void
    {
        $this->authorize('approve', $this->count);

        try {
            $this->count = $workflow->cancel($this->count, auth()->user());
            $this->dispatch('toast', message: "Count {$this->count->number} cancelled.", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        $this->count->load('lines.item', 'lines.location', 'lines.batch', 'lines.uom', 'createdBy', 'virtualWarehouse');

        // Blind count: hide system_qty from the view when status is Open or Counting
        $blind = in_array($this->count->status, [CycleCountStatus::Open, CycleCountStatus::Counting], true);

        return view('livewire.inventory.counts.show', compact('blind'))
            ->layout('components.layouts.app', [
                'title'           => $this->count->number,
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
