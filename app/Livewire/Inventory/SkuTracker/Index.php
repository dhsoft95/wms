<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\SkuTracker;

use App\Enums\GrnStatus;
use App\Enums\Outbound\DoLineStatus;
use App\Models\DeliveryOrderLine;
use App\Models\GrnLine;
use App\Models\Inventory\Batch;
use App\Models\Inventory\SerialNumber;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Item;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url(except: '')]
    public string $search = '';

    #[Url(except: null)]
    public ?int $selectedItemId = null;

    public function updatingSearch(): void
    {
        $this->selectedItemId = null;
    }

    public function selectItem(int $id): void
    {
        $this->selectedItemId = $id;
        $this->search         = '';
    }

    public function clearSelection(): void
    {
        $this->selectedItemId = null;
    }

    // ─── Search Results ───────────────────────────────────────────────────────

    #[Computed]
    public function results()
    {
        $q = trim($this->search);

        if (strlen($q) < 2) {
            return collect();
        }

        return Item::with(['category', 'baseUom'])
            ->where(function ($query) use ($q) {
                $query->where('sku', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('part_number', 'like', "%{$q}%")
                    ->orWhereHas('barcodes', fn ($bq) => $bq->where('barcode', 'like', "%{$q}%"));
            })
            ->active()
            ->orderBy('sku')
            ->limit(20)
            ->get();
    }

    // ─── Selected Item ────────────────────────────────────────────────────────

    #[Computed]
    public function selectedItem(): ?Item
    {
        if ($this->selectedItemId === null) {
            return null;
        }

        return Item::with(['category', 'baseUom'])->find($this->selectedItemId);
    }

    // ─── Stock Summary ────────────────────────────────────────────────────────

    #[Computed]
    public function stockSummary(): array
    {
        if ($this->selectedItemId === null) {
            return ['on_hand' => 0, 'allocated' => 0, 'available' => 0];
        }

        $rows = StockBalance::where('item_id', $this->selectedItemId)->get();

        $onHand    = $rows->sum(fn ($r) => (float) $r->qty_on_hand);
        $allocated = $rows->sum(fn ($r) => (float) $r->qty_allocated);

        return [
            'on_hand'   => $onHand,
            'allocated' => $allocated,
            'available' => $onHand - $allocated,
        ];
    }

    // ─── Stock By Location ────────────────────────────────────────────────────

    #[Computed]
    public function stockByLocation()
    {
        if ($this->selectedItemId === null) {
            return collect();
        }

        return StockBalance::with(['location', 'virtualWarehouse', 'batch'])
            ->where('item_id', $this->selectedItemId)
            ->where('qty_on_hand', '>', 0)
            ->orderByDesc('qty_on_hand')
            ->get();
    }

    // ─── Recent Movements ─────────────────────────────────────────────────────

    #[Computed]
    public function recentMovements()
    {
        if ($this->selectedItemId === null) {
            return collect();
        }

        return StockMovement::with(['transaction', 'location', 'batch'])
            ->where('item_id', $this->selectedItemId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    // ─── Open Inbound ─────────────────────────────────────────────────────────

    #[Computed]
    public function openInbound()
    {
        if ($this->selectedItemId === null) {
            return collect();
        }

        return GrnLine::with(['grn'])
            ->where('item_id', $this->selectedItemId)
            ->whereHas('grn', fn ($q) => $q->whereNotIn('status', [
                GrnStatus::Closed->value,
                GrnStatus::Cancelled->value,
            ]))
            ->orderByDesc('created_at')
            ->get();
    }

    // ─── Open Outbound ────────────────────────────────────────────────────────

    #[Computed]
    public function openOutbound()
    {
        if ($this->selectedItemId === null) {
            return collect();
        }

        return DeliveryOrderLine::with(['deliveryOrder'])
            ->where('item_id', $this->selectedItemId)
            ->whereNotIn('status', [
                DoLineStatus::Dispatched->value,
                DoLineStatus::Cancelled->value,
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    // ─── Batches ──────────────────────────────────────────────────────────────

    #[Computed]
    public function batches()
    {
        if ($this->selectedItemId === null) {
            return collect();
        }

        $stockByBatch = StockBalance::where('item_id', $this->selectedItemId)
            ->whereNotNull('batch_id')
            ->selectRaw('batch_id, SUM(qty_on_hand) as qty_total')
            ->groupBy('batch_id')
            ->pluck('qty_total', 'batch_id');

        return Batch::where('item_id', $this->selectedItemId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Batch $batch) use ($stockByBatch) {
                $batch->qty_on_hand = (float) ($stockByBatch[$batch->id] ?? 0);

                return $batch;
            });
    }

    // ─── Serials ──────────────────────────────────────────────────────────────

    #[Computed]
    public function serials()
    {
        if ($this->selectedItemId === null) {
            return collect();
        }

        return SerialNumber::with(['location', 'batch'])
            ->where('item_id', $this->selectedItemId)
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.inventory.sku-tracker.index')
            ->layout('components.layouts.app', [
                'title'           => 'SKU Tracker',
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
