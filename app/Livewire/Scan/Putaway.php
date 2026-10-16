<?php
declare(strict_types=1);
namespace App\Livewire\Scan;

use App\Enums\LocationLevel;
use App\Enums\PutawayTaskStatus;
use App\Enums\ScanResultType;
use App\Models\Inventory\Lpn;
use App\Models\Location;
use App\Models\PutawayTask;
use App\Services\Inbound\PutawayEngine;
use App\Services\Scanning\BarcodeResolver;
use App\Services\Scanning\ScanContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Putaway extends Component
{
    public string $step        = 'lpn';
    public string $barcode     = '';
    public string $requestId   = '';
    public ?int   $lpnId       = null;
    public ?int   $taskId      = null;
    public ?int   $destinationId = null;

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
            ->with('lpn', 'item')
            ->latest()
            ->get();
    }

    #[Computed]
    public function currentLpn(): ?Lpn
    {
        return $this->lpnId ? Lpn::find($this->lpnId) : null;
    }

    #[Computed]
    public function currentTask(): ?PutawayTask
    {
        return $this->taskId ? PutawayTask::with('suggestedLocation')->find($this->taskId) : null;
    }

    #[Computed]
    public function destination(): ?Location
    {
        return $this->destinationId ? Location::find($this->destinationId) : null;
    }

    public function selectTask(int $taskId): void
    {
        $task = PutawayTask::with('lpn', 'suggestedLocation')->find($taskId);
        if (! $task) return;
        $this->taskId = $task->id;
        $this->lpnId  = $task->lpn_id;
        if ($task->suggested_location_id) {
            $this->destinationId = $task->suggested_location_id;
            $this->step = 'confirm';
        } else {
            $this->step = 'bin';
        }
        $this->dispatch('scan-success');
    }

    public function processScan(): void
    {
        $raw = trim($this->barcode);
        $this->barcode = '';
        if ($raw === '') return;

        $resolver = app(BarcodeResolver::class);

        match ($this->step) {
            'lpn' => $this->handleLpnScan($raw, $resolver),
            'bin' => $this->handleBinScan($raw, $resolver),
            default => null,
        };
    }

    private function handleLpnScan(string $raw, BarcodeResolver $resolver): void
    {
        $result = $resolver->resolve($raw, new ScanContext(expects: ScanResultType::Lpn));
        if ($result->type !== ScanResultType::Lpn) {
            $this->dispatch('toast', message: $result->error ?? 'Not an LPN.', type: 'error');
            $this->dispatch('scan-error');
            return;
        }

        $lpn  = $result->record;
        $task = PutawayTask::where('lpn_id', $lpn->id)
            ->where('status', PutawayTaskStatus::Open->value)
            ->with('suggestedLocation')
            ->first();

        $this->lpnId  = $lpn->id;
        $this->taskId = $task?->id;

        if ($task?->suggested_location_id) {
            $this->destinationId = $task->suggested_location_id;
            $this->step = 'confirm';
        } else {
            $this->step = 'bin';
        }
        $this->dispatch('scan-success');
    }

    private function handleBinScan(string $raw, BarcodeResolver $resolver): void
    {
        $result = $resolver->resolve($raw, new ScanContext(expects: ScanResultType::Location));
        if ($result->type !== ScanResultType::Location) {
            $this->dispatch('toast', message: $result->error ?? 'Not a bin location.', type: 'error');
            $this->dispatch('scan-error');
            return;
        }
        $loc = $result->record;
        if ($loc->status !== 'active') {
            $this->dispatch('toast', message: 'Location is not active.', type: 'error');
            $this->dispatch('scan-error');
            return;
        }
        if ($loc->level !== LocationLevel::Bin) {
            $this->dispatch('toast', message: "Choose a bin, not a {$loc->level->label()}.", type: 'error');
            $this->dispatch('scan-error');
            return;
        }
        $this->destinationId = $loc->id;
        $this->step = 'confirm';
        $this->dispatch('scan-success');
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

        if ($this->taskId) {
            $task = PutawayTask::findOrFail($this->taskId);
            app(PutawayEngine::class)->execute($task, $destination, auth()->user());
        } else {
            // No task — just update LPN location directly
            Lpn::where('id', $this->lpnId)->update(['current_location_id' => $destination->id]);
        }

        $this->dispatch('toast', message: "Putaway to {$destination->code} complete.", type: 'success');
        $this->dispatch('scan-success');
        $this->reset(['lpnId', 'taskId', 'destinationId']);
        $this->step = 'lpn';
        $this->requestId = (string) Str::uuid();
    }

    public function back(): void
    {
        $this->step = match ($this->step) {
            'bin'     => 'lpn',
            'confirm' => $this->taskId ? 'lpn' : 'bin',
            default   => 'lpn',
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
