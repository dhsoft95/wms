<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\Inventory\SerialStatus;
use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Enums\LocationType;
use App\Enums\ScanResultType;
use App\Models\Inventory\SerialNumber;
use App\Models\Location;
use App\Models\PutawayTask;
use App\Services\Inbound\PutawayEngine;
use App\Services\Scanning\BarcodeResolver;
use App\Services\Scanning\ScanContext;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;

trait HasPutawayModal
{
    public bool   $showExecuteModal = false;
    public ?int   $executingTaskId  = null;
    public bool   $hasDraft         = false;
    public string $scanBinInput     = '';

    /** @var array<int, array{locationId: string, locationCode: string, qty: string, serials: array<string>}> */
    public array $splitRows = [];

    /** @var array<string> */
    public array $taskSerials = [];

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function executingTask(): ?PutawayTask
    {
        if ($this->executingTaskId === null) {
            return null;
        }

        return PutawayTask::with(['item', 'fromLocation', 'grn', 'suggestedLocation'])->find($this->executingTaskId);
    }

    // ─── Open / Close ─────────────────────────────────────────────────────────

    public function openExecute(int $taskId): void
    {
        $this->executingTaskId = $taskId;
        unset($this->executingTask);

        $task           = PutawayTask::with('item')->find($taskId);
        $draftKey       = "putaway_draft_{$this->getAuthId()}_{$taskId}";
        $draft          = Cache::get($draftKey);
        $this->hasDraft = $draft !== null;

        if ($draft) {
            $this->splitRows = $draft;
        } else {
            $this->splitRows = [[
                'locationId'   => '',
                'locationCode' => '',
                'qty'          => $task ? rtrim(rtrim(number_format((float) $task->qty, 4), '0'), '.') : '',
                'serials'      => [],
            ]];
        }

        $this->taskSerials = [];
        if ($task?->item?->track_serial) {
            $query = SerialNumber::where('item_id', $task->item_id)
                ->where('location_id', $task->from_location_id)
                ->where('status', SerialStatus::InStock);

            if ($task->batch_id) {
                $query->where('batch_id', $task->batch_id);
            }
            if ($task->lpn_id) {
                $query->where('lpn_id', $task->lpn_id);
            }

            $this->taskSerials = $query->orderBy('serial_no')->limit((int) $task->qty)->pluck('serial_no')->all();
        }

        $this->showExecuteModal = true;
    }

    public function closeExecute(): void
    {
        $this->showExecuteModal = false;
        $this->executingTaskId  = null;
        $this->splitRows        = [];
        $this->taskSerials      = [];
        $this->hasDraft         = false;
        $this->scanBinInput     = '';
        unset($this->executingTask);
    }

    public function processBinScan(int $rowIndex = 0): void
    {
        $input              = trim($this->scanBinInput);
        $this->scanBinInput = '';
        if ($input === '') {
            return;
        }

        $result = app(BarcodeResolver::class)->resolve(
            $input,
            new ScanContext(expects: ScanResultType::Location)
        );

        if ($result->type !== ScanResultType::Location) {
            $this->dispatch('toast', message: $result->error ?? 'Not a bin location.', type: 'error');
            $this->dispatch('scan-error');
            return;
        }

        $loc = $result->record;

        if ($loc->status !== LocationStatus::Active) {
            $this->dispatch('toast', message: "Location {$loc->code} is not active.", type: 'error');
            $this->dispatch('scan-error');
            return;
        }

        if ($loc->level !== LocationLevel::Bin) {
            $this->dispatch('toast', message: "Choose a bin, not a {$loc->level->label()}.", type: 'error');
            $this->dispatch('scan-error');
            return;
        }

        $this->selectSplitBin($rowIndex, $loc->id, $loc->code);
        $this->dispatch('scan-success');
        $this->dispatch('toast', message: "Bin {$loc->code} selected.", type: 'success');
    }

    // ─── Draft ────────────────────────────────────────────────────────────────

    public function saveDraft(): void
    {
        if ($this->executingTaskId === null) {
            return;
        }

        $draftKey = "putaway_draft_{$this->getAuthId()}_{$this->executingTaskId}";
        Cache::put($draftKey, $this->splitRows, now()->addDays(7));
        $this->closeExecute();
        $this->dispatch('toast', message: 'Draft saved. You can resume this putaway later.', type: 'info');
    }

    public function discardDraft(): void
    {
        if ($this->executingTaskId === null) {
            return;
        }

        $draftKey = "putaway_draft_{$this->getAuthId()}_{$this->executingTaskId}";
        Cache::forget($draftKey);
        $this->hasDraft = false;

        $task            = PutawayTask::with('item')->find($this->executingTaskId);
        $this->splitRows = [[
            'locationId'   => '',
            'locationCode' => '',
            'qty'          => $task ? rtrim(rtrim(number_format((float) $task->qty, 4), '0'), '.') : '',
            'serials'      => [],
        ]];
    }

    // ─── Split Rows ───────────────────────────────────────────────────────────

    public function addSplitRow(): void
    {
        $this->splitRows[] = [
            'locationId'   => '',
            'locationCode' => '',
            'qty'          => '',
            'serials'      => [],
        ];
    }

    public function removeSplitRow(int $index): void
    {
        array_splice($this->splitRows, $index, 1);
        $this->splitRows = array_values($this->splitRows);
    }

    public function selectSplitBin(int $rowIndex, int $locationId, string $locationCode): void
    {
        $this->splitRows[$rowIndex]['locationId']   = (string) $locationId;
        $this->splitRows[$rowIndex]['locationCode'] = $locationCode;
    }

    public function clearSplitBin(int $rowIndex): void
    {
        $this->splitRows[$rowIndex]['locationId']   = '';
        $this->splitRows[$rowIndex]['locationCode'] = '';
    }

    public function toggleRowSerial(int $rowIndex, string $serial): void
    {
        $serials = $this->splitRows[$rowIndex]['serials'] ?? [];
        $pos     = array_search($serial, $serials, true);

        if ($pos !== false) {
            array_splice($serials, $pos, 1);
        } else {
            $serials[] = $serial;
        }

        $this->splitRows[$rowIndex]['serials'] = array_values($serials);
    }

    // ─── Bin Search ───────────────────────────────────────────────────────────

    #[Renderless]
    public function getBinSuggestions(string $search = ''): array
    {
        $task        = $this->executingTask;
        $warehouseId = $task?->grn?->warehouse_id;
        $isHazardous = (bool) $task?->item?->is_hazardous;

        $allowedTypes = $isHazardous
            ? [LocationType::Hazardous->value]
            : [LocationType::Storage->value, LocationType::Staging->value];

        $query = Location::where('level', LocationLevel::Bin->value)
            ->whereIn('type', $allowedTypes)
            ->where('status', LocationStatus::Active->value);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
            );
        }

        return $query->orderBy('code')
            ->limit(200)
            ->get(['id', 'code', 'barcode'])
            ->map(fn ($l) => ['id' => $l->id, 'code' => $l->code, 'barcode' => $l->barcode])
            ->toArray();
    }

    // ─── Execute ──────────────────────────────────────────────────────────────

    public function executeSplitTask(): void
    {
        $task = $this->executingTask;
        if (! $task) {
            return;
        }

        $serialDriven = ! empty($this->taskSerials);
        $rows         = [];

        foreach ($this->splitRows as $i => $row) {
            $locationId = (int) ($row['locationId'] ?? 0);
            $serials    = array_values(array_filter((array) ($row['serials'] ?? [])));

            if ($locationId <= 0) {
                $this->dispatch('toast', message: 'Row ' . ($i + 1) . ': select a destination bin.', type: 'error');
                return;
            }

            if ($serialDriven) {
                if (empty($serials)) {
                    continue;
                }
                $qty = (string) count($serials);
            } else {
                $qty = trim((string) ($row['qty'] ?? ''));
                if ($qty === '' || (float) $qty <= 0) {
                    $this->dispatch('toast', message: 'Row ' . ($i + 1) . ': enter a valid quantity.', type: 'error');
                    return;
                }
            }

            $rows[] = [
                'location_id' => $locationId,
                'qty'         => $qty,
                'serials'     => $serials,
            ];
        }

        try {
            app(PutawayEngine::class)->executeSplits($task, $rows, auth()->user());

            $draftKey = "putaway_draft_{$this->getAuthId()}_{$this->executingTaskId}";
            Cache::forget($draftKey);

            $this->closeExecute();
            $this->dispatch('toast', message: 'Putaway complete!', type: 'success');
            $this->afterPutawayExecuted();
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Error: ' . $e->getMessage(), type: 'error');
        }
    }

    protected function afterPutawayExecuted(): void
    {
        // Override in component to refresh related state
    }

    private function getAuthId(): int|string
    {
        return auth()->id() ?? 0;
    }
}
