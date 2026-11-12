<?php
declare(strict_types=1);
namespace App\Livewire\Scan;

use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\PutawayTaskStatus;
use App\Enums\ScanResultType;
use App\Livewire\Concerns\HandlesScans;
use App\Models\Inventory\Lpn;
use App\Models\Inventory\StockBalance;
use App\Models\Location;
use App\Models\PutawayTask;
use App\Services\Inbound\PutawayEngine;
use App\Services\Scanning\RelocationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Putaway extends Component
{
    use HandlesScans;

    public string $step                = 'lpn_or_item';
    public string $requestId           = '';
    public ?int   $taskId              = null;
    public ?int   $lpnId               = null;
    public ?int   $suggestedLocationId = null;
    public ?int   $destinationId       = null;
    public string $overrideReason      = '';

    public function mount(): void
    {
        $this->authorize('scan.putaway');
        $this->requestId = (string) Str::uuid();
    }

    #[Computed]
    public function myTasks(): \Illuminate\Database\Eloquent\Collection
    {
        return PutawayTask::where('assigned_to', auth()->id())
            ->where('status', PutawayTaskStatus::Open->value)
            ->with('item')
            ->latest()
            ->get();
    }

    #[Computed]
    public function currentTask(): ?PutawayTask
    {
        return $this->taskId ? PutawayTask::with('suggestedLocation', 'item')->find($this->taskId) : null;
    }

    #[Computed]
    public function currentLpn(): ?Lpn
    {
        return $this->lpnId ? Lpn::with('currentLocation')->find($this->lpnId) : null;
    }

    #[Computed]
    public function lpnContents(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->lpnId) return collect();
        return StockBalance::where('lpn_id', $this->lpnId)
            ->where('qty_on_hand', '>', 0)
            ->with('item')
            ->get();
    }

    #[Computed]
    public function suggestedLocation(): ?Location
    {
        return $this->suggestedLocationId ? Location::find($this->suggestedLocationId) : null;
    }

    #[Computed]
    public function destination(): ?Location
    {
        return $this->destinationId ? Location::find($this->destinationId) : null;
    }

    public function selectTask(int $taskId): void
    {
        $task = PutawayTask::with('suggestedLocation')->find($taskId);
        if (! $task) return;

        $this->taskId = $task->id;
        $suggested = app(PutawayEngine::class)->suggest($task);
        $this->suggestedLocationId = $suggested?->id;

        if ($this->suggestedLocationId) {
            $this->destinationId = $this->suggestedLocationId;
            $this->step = 'confirm';
        } else {
            $this->step = 'bin';
        }
        $this->scanOk();
    }

    public function processScan(): void
    {
        match ($this->step) {
            'lpn_or_item' => $this->handleLpnOrItemScan(),
            'bin'         => $this->handleBinScan(),
            default       => null,
        };
    }

    private function handleLpnOrItemScan(): void
    {
        $result = $this->resolveScan(null);
        if ($result === null) return;

        if ($result->type === ScanResultType::Lpn) {
            $lpn = $result->record;
            $this->lpnId = $lpn->id;

            $task = PutawayTask::where('lpn_id', $lpn->id)
                ->where('status', PutawayTaskStatus::Open->value)
                ->first();

            if ($task) {
                $this->taskId = $task->id;
                $suggested = app(PutawayEngine::class)->suggest($task);
            } else {
                $suggested = $this->suggestBinForWarehouse($lpn->warehouse_id);
            }

            $this->suggestedLocationId = $suggested?->id;
            $this->step = 'bin';
            $this->scanOk();
            return;
        }

        if ($result->type === ScanResultType::Item) {
            $item = $result->record;
            $task = PutawayTask::where('item_id', $item->id)
                ->where('status', PutawayTaskStatus::Open->value)
                ->first();

            if (! $task) {
                $this->scanFail("No open putaway task for {$item->sku}.");
                return;
            }

            $this->taskId = $task->id;
            $suggested = app(PutawayEngine::class)->suggest($task);
            $this->suggestedLocationId = $suggested?->id;
            $this->step = 'bin';
            $this->scanOk();
            return;
        }

        $this->scanFail($result->error ?? 'Scan an LPN or item barcode.');
    }

    private function handleBinScan(): void
    {
        $result = $this->resolveScan(ScanResultType::Location);
        if ($result === null) return;

        if ($result->type !== ScanResultType::Location) {
            $this->scanFail($result->error ?? 'Not a bin location.');
            return;
        }

        $loc = $result->record;
        if ($loc->status !== 'active') {
            $this->scanFail('Location is not active.');
            return;
        }
        if ($loc->level !== LocationLevel::Bin) {
            $this->scanFail("Choose a bin, not a {$loc->level->label()}.");
            return;
        }

        $this->destinationId = $loc->id;

        if ($this->suggestedLocationId && $loc->id !== $this->suggestedLocationId) {
            $this->step = 'bin_override';
            $this->scanOk('Bin differs from suggestion — state reason to continue.');
        } else {
            $this->step = 'confirm';
            $this->scanOk();
        }
    }

    public function acceptOverride(): void
    {
        if (trim($this->overrideReason) === '') {
            $this->scanFail('Please enter a reason for overriding the suggested bin.');
            return;
        }
        $this->step = 'confirm';
    }

    public function confirmPutaway(): void
    {
        $cacheKey = "scan_req:{$this->requestId}";
        if (Cache::has($cacheKey)) {
            $this->dispatch('toast', message: 'Already saved.', type: 'info');
            return;
        }
        Cache::put($cacheKey, true, 60);

        $destination = Location::findOrFail($this->destinationId);

        try {
            if ($this->taskId) {
                $task = PutawayTask::findOrFail($this->taskId);
                app(PutawayEngine::class)->execute($task, $destination, auth()->user());
            } else {
                $lpn = Lpn::findOrFail($this->lpnId);
                app(RelocationService::class)->moveLpn($lpn, $destination, auth()->user(), $this->requestId, session('scan_device_code'));
            }
        } catch (\DomainException $e) {
            Cache::forget($cacheKey);
            $this->scanFail($e->getMessage());
            return;
        }

        $this->dispatch('toast', message: "Putaway to {$destination->code} complete.", type: 'success');
        $this->scanOk();
        $this->reset(['taskId', 'lpnId', 'suggestedLocationId', 'destinationId', 'overrideReason']);
        $this->step = 'lpn_or_item';
        $this->requestId = (string) Str::uuid();
    }

    private function suggestBinForWarehouse(?int $warehouseId): ?Location
    {
        if (! $warehouseId) return null;
        return Location::where('warehouse_id', $warehouseId)
            ->where('level', LocationLevel::Bin->value)
            ->where('type', LocationType::Storage->value)
            ->where('status', LocationStatus::Active->value)
            ->first();
    }

    public function back(): void
    {
        if ($this->step === 'lpn_or_item') {
            $this->redirect(route('scan.home'), navigate: true);
            return;
        }

        $this->step = match ($this->step) {
            'bin'          => 'lpn_or_item',
            'bin_override' => 'bin',
            'confirm'      => $this->overrideReason !== '' ? 'bin_override' : 'bin',
            default        => 'lpn_or_item',
        };
    }

    public function cancel(): void
    {
        $this->redirect(route('scan.home'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.scan.putaway')
            ->layout('components.layouts.scan', ['title' => 'Putaway']);
    }
}
