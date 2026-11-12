<?php
declare(strict_types=1);
namespace App\Livewire\Scan;

use App\Enums\GrnStatus;
use App\Enums\Inventory\SerialStatus;
use App\Enums\ScanResultType;
use App\Livewire\Concerns\HandlesScans;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\Inventory\Lpn;
use App\Models\Inventory\SerialNumber;
use App\Services\Inbound\GrnWorkflow;
use App\Services\Inbound\LpnService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Receive extends Component
{
    use HandlesScans;

    public string $step      = 'grn';
    public string $requestId = '';

    // Selections
    public ?int    $grnId       = null;
    public ?int    $grnLineId   = null;
    public ?int    $itemId      = null;
    public string  $qty         = '';
    public string  $batchNo     = '';
    public string  $expiryDate  = '';
    public string  $condition   = 'good';
    public ?int    $lpnId       = null;
    public bool    $newLpn      = false;
    public ?array  $gs1Data     = null;

    /** Accumulated serial numbers during the 'serials' step */
    public array $serials = [];

    public function mount(): void
    {
        $this->authorize('scan.receive');
        $this->requestId = (string) Str::uuid();
    }

    #[Computed]
    public function openGrns(): \Illuminate\Database\Eloquent\Collection
    {
        return Grn::where('status', GrnStatus::Receiving->value)
            ->with('warehouse')
            ->latest()
            ->get();
    }

    #[Computed]
    public function currentGrn(): ?Grn
    {
        return $this->grnId ? Grn::find($this->grnId) : null;
    }

    #[Computed]
    public function currentItem(): ?\App\Models\Item
    {
        return $this->itemId ? \App\Models\Item::find($this->itemId) : null;
    }

    #[Computed]
    public function currentLpn(): ?Lpn
    {
        return $this->lpnId ? Lpn::find($this->lpnId) : null;
    }

    /** How many serials are required (= base qty, integer) */
    #[Computed]
    public function requiredSerials(): int
    {
        return max(1, (int) ceil((float) $this->qty));
    }

    public function selectGrn(int $id): void
    {
        $this->grnId = $id;
        $this->step  = 'item';
        $this->scanOk();
    }

    public function processScan(): void
    {
        match ($this->step) {
            'grn'     => $this->handleGrnScan(),
            'item'    => $this->handleItemScan(),
            'serials' => $this->handleSerialScan(),
            'lpn'     => $this->handleLpnScan(),
            default   => null,
        };
    }

    private function handleGrnScan(): void
    {
        $result = $this->resolveScan(ScanResultType::Grn);
        if ($result === null) return;

        if ($result->type === ScanResultType::Grn) {
            $grn = $result->record;
            if ($grn->status !== GrnStatus::Receiving) {
                $this->scanFail('GRN is not in Receiving status.');
                return;
            }
            $this->grnId = $grn->id;
            $this->step  = 'item';
            $this->scanOk();
        } else {
            $this->scanFail($result->error ?? 'Not a GRN barcode.');
        }
    }

    private function handleItemScan(): void
    {
        $result = $this->resolveScan(ScanResultType::Item);
        if ($result === null) return;

        if ($result->type !== ScanResultType::Item) {
            $this->scanFail($result->error ?? 'Item not recognised.');
            return;
        }

        $item = $result->record;
        $line = GrnLine::where('grn_id', $this->grnId)->where('item_id', $item->id)->first();

        if (! $line) {
            $this->scanFail("Item {$item->sku} not on this GRN.");
            return;
        }

        $this->itemId    = $item->id;
        $this->grnLineId = $line->id;
        $this->gs1Data   = $result->gs1Data;

        // Pre-fill from GS1
        if ($result->gs1Data) {
            $this->qty        = $result->gs1Data['qty'] ? (string) $result->gs1Data['qty'] : '';
            $this->batchNo    = $result->gs1Data['batch'] ?? '';
            $this->expiryDate = $result->gs1Data['expiry'] ?? '';
        }

        $this->step = 'qty';
        $this->scanOk();
    }

    private function handleSerialScan(): void
    {
        // Serials are raw strings — read scanInput directly (not via resolver)
        $raw = trim($this->scanInput);
        $this->scanInput = '';
        if ($raw === '') return;

        $serial = SerialNumber::normalize($raw);

        // Duplicate within this receipt
        if (in_array($serial, $this->serials, true)) {
            $this->scanFail("Serial {$serial} already scanned in this receipt.");
            return;
        }

        // Already in stock
        $inStock = SerialNumber::where('item_id', $this->itemId)
            ->where('serial_no', $serial)
            ->where('status', SerialStatus::InStock)
            ->exists();
        if ($inStock) {
            $this->scanFail("Serial {$serial} is already in stock.");
            return;
        }

        $this->serials[] = $serial;
        $count           = count($this->serials);
        $required        = $this->requiredSerials;

        if ($count >= $required) {
            $this->step = 'lpn';
            $this->scanOk("All {$required} serial(s) scanned.");
        } else {
            $this->scanOk("{$count} of {$required} serial(s) scanned.");
        }
    }

    private function handleLpnScan(): void
    {
        $result = $this->resolveScan(ScanResultType::Lpn);
        if ($result === null) return;

        if ($result->type === ScanResultType::Lpn) {
            $this->lpnId  = $result->record->id;
            $this->newLpn = false;
            $this->step   = 'confirm';
            $this->scanOk();
        } else {
            $this->scanFail($result->error ?? 'LPN not recognised.');
        }
    }

    public function setQty(string $qty): void
    {
        // Validate expiry not in past when pre-filled from GS1
        if ($this->expiryDate && \Carbon\Carbon::parse($this->expiryDate)->startOfDay()->isPast()) {
            $this->scanFail("Expiry date {$this->expiryDate} is in the past.");
            return;
        }

        $this->qty  = $qty;
        $this->step = 'condition';
    }

    public function setCondition(string $condition): void
    {
        $this->condition = $condition;
        $item = $this->currentItem;

        if ($item?->track_serial) {
            $this->serials = [];
            $this->step    = 'serials';
        } else {
            $this->step = 'lpn';
        }
    }

    public function createNewLpn(): void
    {
        $grn         = $this->currentGrn;
        $lpn         = app(LpnService::class)->generate($grn);
        $this->lpnId  = $lpn->id;
        $this->newLpn = true;
        $this->step   = 'confirm';
        $this->scanOk();
    }

    public function saveReceipt(): void
    {
        $cacheKey = "scan_req:{$this->requestId}";
        if (Cache::has($cacheKey)) {
            $this->dispatch('toast', message: 'Already saved.', type: 'info');
            return;
        }
        Cache::put($cacheKey, true, 60);

        $this->validate([
            'grnLineId' => 'required|exists:grn_lines,id',
            'qty'       => 'required|numeric|min:0.0001',
            'condition' => 'required|in:good,damaged',
            'lpnId'     => 'required|exists:lpns,id',
        ]);

        $line = GrnLine::findOrFail($this->grnLineId);
        $item = \App\Models\Item::findOrFail($this->itemId);

        try {
            app(GrnWorkflow::class)->addReceipt(
                line:      $line,
                qty:       $this->qty,
                uomId:     $item->base_uom_id,
                condition: $this->condition,
                batchNo:   $this->batchNo ?: null,
                expiryDate: $this->expiryDate ?: null,
                lpnId:     $this->lpnId,
                serialNos: $this->serials,
                device:    session('scan_device_code'),
            );
        } catch (\DomainException $e) {
            Cache::forget($cacheKey); // allow retry after correction
            $this->scanFail($e->getMessage());
            return;
        }

        $this->dispatch('toast', message: "Receipt saved for {$item->sku}.", type: 'success');
        $this->scanOk();
        $this->resetForNextItem();
    }

    private function resetForNextItem(): void
    {
        $grnId = $this->grnId;
        $this->reset(['itemId', 'grnLineId', 'qty', 'batchNo', 'expiryDate', 'condition',
                      'lpnId', 'newLpn', 'gs1Data', 'serials']);
        $this->grnId     = $grnId;
        $this->requestId = (string) Str::uuid();
        $this->step      = 'item';
    }

    public function back(): void
    {
        if ($this->step === 'grn') {
            $this->redirect(route('scan.home'), navigate: true);
            return;
        }

        $this->step = match ($this->step) {
            'item'      => 'grn',
            'qty'       => 'item',
            'condition' => 'qty',
            'serials'   => 'condition',
            'lpn'       => $this->currentItem?->track_serial ? 'serials' : 'condition',
            'confirm'   => 'lpn',
            default     => 'grn',
        };
    }

    public function cancel(): void
    {
        $this->redirect(route('scan.home'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.scan.receive')
            ->layout('components.layouts.scan', ['title' => 'Receive']);
    }
}
