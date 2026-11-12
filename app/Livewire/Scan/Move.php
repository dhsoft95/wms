<?php
declare(strict_types=1);
namespace App\Livewire\Scan;

use App\Enums\ScanResultType;
use App\Livewire\Concerns\HandlesScans;
use App\Models\Inventory\Lpn;
use App\Models\Inventory\StockBalance;
use App\Models\Location;
use App\Models\VirtualWarehouse;
use App\Services\Scanning\RelocationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Move extends Component
{
    use HandlesScans;

    public string $step      = 'source_bin';
    public string $requestId = '';

    public ?int   $sourceBinId   = null;
    public ?int   $lpnId         = null;
    public ?int   $itemId        = null;
    public ?int   $batchId       = null;
    public ?int   $vwId          = null;
    public string $qty           = '';
    public ?int   $destBinId     = null;
    public array  $stockOptions  = [];  // [['id'=>x,'name'=>y], ...] when multiple VWs

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
        match ($this->step) {
            'source_bin'  => $this->handleSourceBin(),
            'lpn_or_item' => $this->handleLpnOrItem(),
            'dest_bin'    => $this->handleDestBin(),
            default       => null,
        };
    }

    private function handleSourceBin(): void
    {
        $result = $this->resolveScan(ScanResultType::Location);
        if ($result === null) return;

        if ($result->type !== ScanResultType::Location) {
            $this->scanFail($result->error ?? 'Not a location.');
            return;
        }
        $this->sourceBinId = $result->record->id;
        $this->step = 'lpn_or_item';
        $this->scanOk();
    }

    private function handleLpnOrItem(): void
    {
        $result = $this->resolveScan(null);
        if ($result === null) return;

        if ($result->type === ScanResultType::Lpn) {
            $this->lpnId  = $result->record->id;
            $this->itemId = null;
            $this->qty    = '';
            $this->step   = 'dest_bin';
            $this->scanOk();
            return;
        }

        if ($result->type === ScanResultType::Item) {
            $item = $result->record;
            $vwIds = StockBalance::where('item_id', $item->id)
                ->where('location_id', $this->sourceBinId)
                ->where('qty_on_hand', '>', 0)
                ->pluck('virtual_warehouse_id')
                ->unique()
                ->values()
                ->all();

            if (empty($vwIds)) {
                $this->scanFail("No stock for {$item->sku} at this bin.");
                return;
            }

            $this->itemId = $item->id;
            $this->lpnId  = null;
            $this->qty    = '';

            if (count($vwIds) === 1) {
                $this->vwId = $vwIds[0];
                $this->step = 'dest_bin';
                $this->scanOk();
            } else {
                $vws = VirtualWarehouse::whereIn('id', $vwIds)->get(['id', 'name', 'code']);
                $this->stockOptions = $vws->map(fn ($v) => ['id' => $v->id, 'name' => "{$v->code} — {$v->name}"])->all();
                $this->step = 'pick_vw';
                $this->scanOk('Multiple virtual warehouses — please select one.');
            }
            return;
        }

        $this->scanFail($result->error ?? 'Scan an LPN or item.');
    }

    private function handleDestBin(): void
    {
        $result = $this->resolveScan(ScanResultType::Location);
        if ($result === null) return;

        if ($result->type !== ScanResultType::Location) {
            $this->scanFail($result->error ?? 'Not a location.');
            return;
        }
        $loc = $result->record;
        if ($loc->status !== 'active') {
            $this->scanFail('Destination not active.');
            return;
        }
        $this->destBinId = $loc->id;
        $this->step = 'confirm';
        $this->scanOk();
    }

    public function pickVw(int $vwId): void
    {
        $this->vwId = $vwId;
        $this->stockOptions = [];
        $this->step = 'dest_bin';
        $this->scanOk();
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

        try {
            if ($this->lpnId) {
                $lpn  = Lpn::findOrFail($this->lpnId);
                $dest = Location::findOrFail($this->destBinId);
                $service->moveLpn($lpn, $dest, auth()->user(), $this->requestId, session('scan_device_code'));
                $msg = "Pallet {$lpn->code} moved to {$dest->code}.";
            } else {
                $this->validate(['qty' => 'required|numeric|min:0.0001']);
                $item = \App\Models\Item::findOrFail($this->itemId);
                $dest = Location::findOrFail($this->destBinId);
                $service->moveItem(
                    itemId:             $this->itemId,
                    batchId:            $this->batchId,
                    fromLocationId:     $this->sourceBinId,
                    toLocationId:       $this->destBinId,
                    virtualWarehouseId: $this->vwId,
                    qty:                $this->qty,
                    uomId:              $item->base_uom_id,
                    serials:            [],
                    user:               auth()->user(),
                    requestId:          $this->requestId,
                    device:             session('scan_device_code'),
                );
                $msg = "{$this->qty} x {$item->sku} moved to {$dest->code}.";
            }
        } catch (\DomainException $e) {
            Cache::forget($cacheKey);
            $this->scanFail($e->getMessage());
            return;
        }

        $this->dispatch('toast', message: $msg, type: 'success');
        $this->scanOk();
        $this->reset(['sourceBinId', 'lpnId', 'itemId', 'batchId', 'vwId', 'qty', 'destBinId', 'stockOptions']);
        $this->step = 'source_bin';
        $this->requestId = (string) Str::uuid();
    }

    public function back(): void
    {
        if ($this->step === 'source_bin') {
            $this->redirect(route('scan.home'), navigate: true);
            return;
        }

        $this->step = match ($this->step) {
            'lpn_or_item' => 'source_bin',
            'pick_vw'     => 'lpn_or_item',
            'dest_bin'    => $this->stockOptions !== [] ? 'pick_vw' : 'lpn_or_item',
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
