<?php
declare(strict_types=1);
namespace App\Livewire\Scan;

use App\Enums\ScanResultType;
use App\Models\Inventory\Lpn;
use App\Models\Location;
use App\Services\Scanning\BarcodeResolver;
use App\Services\Scanning\RelocationService;
use App\Services\Scanning\ScanContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Move extends Component
{
    public string $step      = 'source_bin';
    public string $barcode   = '';
    public string $requestId = '';

    public ?int   $sourceBinId = null;
    public ?int   $lpnId       = null;   // set if moving whole pallet
    public ?int   $itemId      = null;   // set if moving partial item
    public ?int   $batchId     = null;
    public ?int   $vwId        = null;
    public string $qty         = '';
    public ?int   $destBinId   = null;

    public function mount(): void
    {
        $this->authorize('scan.move');
        $this->requestId = (string) Str::uuid();
    }

    #[Computed]
    public function sourceBin(): ?Location
    {
        return $this->sourceBinId ? Location::find($this->sourceBinId) : null;
    }

    #[Computed]
    public function destBin(): ?Location
    {
        return $this->destBinId ? Location::find($this->destBinId) : null;
    }

    #[Computed]
    public function currentLpn(): ?Lpn
    {
        return $this->lpnId ? Lpn::find($this->lpnId) : null;
    }

    #[Computed]
    public function currentItem(): ?\App\Models\Item
    {
        return $this->itemId ? \App\Models\Item::find($this->itemId) : null;
    }

    public function processScan(): void
    {
        $raw = trim($this->barcode);
        $this->barcode = '';
        if ($raw === '') return;

        $resolver = app(BarcodeResolver::class);

        match ($this->step) {
            'source_bin'  => $this->handleSourceBin($raw, $resolver),
            'lpn_or_item' => $this->handleLpnOrItem($raw, $resolver),
            'dest_bin'    => $this->handleDestBin($raw, $resolver),
            default       => null,
        };
    }

    private function handleSourceBin(string $raw, BarcodeResolver $resolver): void
    {
        $result = $resolver->resolve($raw, new ScanContext(expects: ScanResultType::Location));
        if ($result->type !== ScanResultType::Location) {
            $this->dispatch('toast', message: $result->error ?? 'Not a location.', type: 'error');
            $this->dispatch('scan-error');
            return;
        }
        $this->sourceBinId = $result->record->id;
        $this->step = 'lpn_or_item';
        $this->dispatch('scan-success');
    }

    private function handleLpnOrItem(string $raw, BarcodeResolver $resolver): void
    {
        $result = $resolver->resolve($raw);
        if ($result->type === ScanResultType::Lpn) {
            $this->lpnId  = $result->record->id;
            $this->itemId = null;
            $this->qty    = '';
            $this->step   = 'dest_bin';
            $this->dispatch('scan-success');
        } elseif ($result->type === ScanResultType::Item) {
            $this->itemId = $result->record->id;
            $this->lpnId  = null;
            $this->qty    = '';
            $this->step   = 'dest_bin';
            $this->dispatch('scan-success');
        } else {
            $this->dispatch('toast', message: $result->error ?? 'Scan an LPN or item.', type: 'error');
            $this->dispatch('scan-error');
        }
    }

    private function handleDestBin(string $raw, BarcodeResolver $resolver): void
    {
        $result = $resolver->resolve($raw, new ScanContext(expects: ScanResultType::Location));
        if ($result->type !== ScanResultType::Location) {
            $this->dispatch('toast', message: $result->error ?? 'Not a location.', type: 'error');
            $this->dispatch('scan-error');
            return;
        }
        $loc = $result->record;
        if ($loc->status !== 'active') {
            $this->dispatch('toast', message: 'Destination not active.', type: 'error');
            $this->dispatch('scan-error');
            return;
        }
        $this->destBinId = $loc->id;
        $this->step = 'confirm';
        $this->dispatch('scan-success');
    }

    public function confirmMove(): void
    {
        $cacheKey = "scan_req:{$this->requestId}";
        if (Cache::has($cacheKey)) {
            $this->dispatch('toast', message: 'Already saved.', type: 'info');
            return;
        }
        Cache::put($cacheKey, true, 60);

        $service = app(RelocationService::class);

        if ($this->lpnId) {
            $lpn  = Lpn::findOrFail($this->lpnId);
            $dest = Location::findOrFail($this->destBinId);
            $service->moveLpn($lpn, $dest, auth()->user(), $this->requestId);
            $msg = "Pallet {$lpn->code} moved to {$dest->code}.";
        } else {
            $this->validate(['qty' => 'required|numeric|min:0.0001']);
            $item = \App\Models\Item::findOrFail($this->itemId);
            $service->moveItem(
                itemId: $this->itemId,
                batchId: $this->batchId,
                fromLocationId: $this->sourceBinId,
                toLocationId: $this->destBinId,
                virtualWarehouseId: 1, // default VW — improve in Module 7
                qty: $this->qty,
                uomId: $item->base_uom_id,
                serials: [],
                user: auth()->user(),
                requestId: $this->requestId,
            );
            $msg = "{$this->qty} x {$item->sku} moved.";
        }

        $this->dispatch('toast', message: $msg, type: 'success');
        $this->dispatch('scan-success');
        $this->reset(['sourceBinId', 'lpnId', 'itemId', 'batchId', 'vwId', 'qty', 'destBinId']);
        $this->step = 'source_bin';
        $this->requestId = (string) Str::uuid();
    }

    public function back(): void
    {
        $this->step = match ($this->step) {
            'lpn_or_item' => 'source_bin',
            'dest_bin'    => 'lpn_or_item',
            'confirm'     => 'dest_bin',
            default       => 'source_bin',
        };
    }

    public function cancel(): void
    {
        $this->redirect(route('scan.home'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.scan.move')
            ->layout('components.layouts.scan', ['title' => 'Move']);
    }
}
