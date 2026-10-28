<?php
declare(strict_types=1);
namespace App\Livewire\Scan;

use App\Enums\LocationLevel;
use App\Enums\PutawayTaskStatus;
use App\Enums\ScanResultType;
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
    public string $step           = 'item';
    public string $barcode        = '';
    public string $requestId      = '';
    public ?int   $taskId         = null;
    public ?int   $destinationId  = null;

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
        return $this->taskId ? PutawayTask::with('suggestedLocation')->find($this->taskId) : null;
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
            'item' => $this->handleItemScan($raw, $resolver),
            'bin'  => $this->handleBinScan($raw, $resolver),
            default => null,
        };
    }

    private function handleItemScan(string $raw, BarcodeResolver $resolver): void
    {
        $result = $resolver->resolve($raw, new ScanContext(expects: ScanResultType::Item));
        if ($result->type !== ScanResultType::Item) {
            $this->dispatch('toast', message: $result->error ?? 'Not an item barcode.', type: 'error');
            $this->dispatch('scan-error');
            return;
        }

        $task = PutawayTask::where('item_id', $result->record->id)
            ->where('status', PutawayTaskStatus::Open->value)
            ->with('suggestedLocation')
            ->first();

        if (! $task) {
            $this->dispatch('toast', message: 'No open putaway task for this item.', type: 'error');
            $this->dispatch('scan-error');
            return;
        }

        $this->taskId = $task->id;

        if ($task->suggested_location_id) {
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

        $task = PutawayTask::findOrFail($this->taskId);
        app(PutawayEngine::class)->execute($task, $destination, auth()->user());

        $this->dispatch('toast', message: "Putaway to {$destination->code} complete.", type: 'success');
        $this->dispatch('scan-success');
        $this->reset(['taskId', 'destinationId']);
        $this->step = 'item';
        $this->requestId = (string) Str::uuid();
    }

    public function back(): void
    {
        $this->step = match ($this->step) {
            'bin'     => 'item',
            'confirm' => 'bin',
            default   => 'item',
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
