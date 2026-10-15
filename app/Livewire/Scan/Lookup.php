<?php
declare(strict_types=1);
namespace App\Livewire\Scan;

use App\Enums\ScanResultType;
use App\Services\Scanning\BarcodeResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Lookup extends Component
{
    public string  $barcode    = '';
    public ?string $resultType = null;
    public ?int    $resultId   = null;
    public ?string $errorMsg   = null;
    public ?array  $gs1Data    = null;

    public function mount(): void
    {
        $this->authorize('scan.lookup');
    }

    public function lookup(): void
    {
        $raw = trim($this->barcode);
        $this->barcode = '';
        if ($raw === '') return;

        $result = app(BarcodeResolver::class)->resolve($raw);

        if (! $result->ok()) {
            $this->errorMsg   = $result->error ?? 'Barcode not recognised.';
            $this->resultType = null;
            $this->resultId   = null;
            $this->dispatch('scan-error');
            return;
        }

        $this->resultType = $result->type->value;
        $this->resultId   = $result->record?->getKey();
        $this->gs1Data    = $result->gs1Data;
        $this->errorMsg   = null;
        $this->dispatch('scan-success');
    }

    public function getResultData(): array
    {
        if (! $this->resultType || ! $this->resultId) return [];

        return match ($this->resultType) {
            'lpn'       => $this->lpnData(),
            'item'      => $this->itemStockData(),
            'location'  => $this->locationData(),
            'serial'    => $this->serialData(),
            'container' => $this->containerData(),
            'grn'       => $this->grnData(),
            default     => [],
        };
    }

    private function lpnData(): array
    {
        $lpn = \App\Models\Inventory\Lpn::with('warehouse', 'currentLocation')->find($this->resultId);
        return $lpn ? ['lpn' => $lpn] : [];
    }

    private function itemStockData(): array
    {
        $item  = \App\Models\Item::find($this->resultId);
        $stock = \App\Models\Inventory\StockBalance::with(['location', 'virtualWarehouse'])
            ->where('item_id', $this->resultId)
            ->where('qty_on_hand', '>', 0)
            ->get()
            ->map(fn ($b) => (object) [
                'location'      => $b->location?->code,
                'vw'            => $b->virtualWarehouse?->name,
                'qty_on_hand'   => $b->qty_on_hand,
                'qty_allocated' => $b->qty_allocated,
                'qty_on_hold'   => $b->qty_on_hold,
            ]);
        return ['item' => $item, 'stock' => $stock];
    }

    private function locationData(): array
    {
        $loc      = \App\Models\Location::find($this->resultId);
        $contents = \App\Models\Inventory\StockBalance::with(['item', 'lpn'])
            ->where('location_id', $this->resultId)
            ->where('qty_on_hand', '>', 0)
            ->get()
            ->map(fn ($b) => (object) [
                'sku'          => $b->item?->sku,
                'name'         => $b->item?->name,
                'lpn_code'     => $b->lpn?->code,
                'qty_on_hand'  => $b->qty_on_hand,
                'stock_status' => $b->stock_status,
            ]);
        return ['location' => $loc, 'contents' => $contents];
    }

    private function serialData(): array
    {
        $serial = \App\Models\Inventory\SerialNumber::with('item')->find($this->resultId);
        return $serial ? ['serial' => $serial] : [];
    }

    private function containerData(): array
    {
        $container = \App\Models\Container::with('yardSlot', 'warehouse')->find($this->resultId);
        return $container ? ['container' => $container] : [];
    }

    private function grnData(): array
    {
        $grn = \App\Models\Grn::with('warehouse', 'supplierPartner')->find($this->resultId);
        return $grn ? ['grn' => $grn] : [];
    }

    public function render(): View
    {
        return view('livewire.scan.lookup', ['data' => $this->getResultData()])
            ->layout('components.layouts.scan', ['title' => 'Lookup']);
    }
}
