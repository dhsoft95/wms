<?php
declare(strict_types=1);
namespace App\Livewire\Scan;

use App\Enums\GrnStatus;
use App\Enums\ScanResultType;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\GrnReceipt;
use App\Models\Inventory\Lpn;
use App\Services\Inbound\LpnService;
use App\Services\Scanning\BarcodeResolver;
use App\Services\Scanning\ScanContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Receive extends Component
{
    public string $step      = 'grn';
    public string $barcode   = '';
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

    public function selectGrn(int $id): void
    {
        $this->grnId = $id;
        $this->step  = 'item';
        $this->dispatch('scan-success');
    }

    public function processScan(): void
    {
        $raw = trim($this->barcode);
        $this->barcode = '';
        if ($raw === '') return;

        $resolver = app(BarcodeResolver::class);

        match ($this->step) {
            'grn'  => $this->handleGrnScan($raw, $resolver),
            'item' => $this->handleItemScan($raw, $resolver),
            'lpn'  => $this->handleLpnScan($raw, $resolver),
            default => null,
        };
    }

    private function handleGrnScan(string $raw, BarcodeResolver $resolver): void
    {
        $result = $resolver->resolve($raw, new ScanContext(expects: ScanResultType::Grn));
        if ($result->type === ScanResultType::Grn) {
            $grn = $result->record;
            if ($grn->status->value !== GrnStatus::Receiving->value) {
                $this->dispatch('toast', message: 'GRN is not in Receiving status.', type: 'error');
                $this->dispatch('scan-error');
                return;
            }
            $this->grnId = $grn->id;
            $this->step  = 'item';
            $this->dispatch('scan-success');
        } else {
            $this->dispatch('toast', message: $result->error ?? 'Not a GRN barcode.', type: 'error');
            $this->dispatch('scan-error');
        }
    }

    private function handleItemScan(string $raw, BarcodeResolver $resolver): void
    {
        $result = $resolver->resolve($raw, new ScanContext(expects: ScanResultType::Item));
        if ($result->type !== ScanResultType::Item) {
            $this->dispatch('toast', message: $result->error ?? 'Item not recognised.', type: 'error');
            $this->dispatch('scan-error');
            return;
        }

        $item = $result->record;

        // Resolve GRN line — find open line for this item in the selected GRN
        $line = GrnLine::where('grn_id', $this->grnId)
            ->where('item_id', $item->id)
            ->first();

        if (! $line) {
            $this->dispatch('toast', message: "Item {$item->sku} not on this GRN.", type: 'error');
            $this->dispatch('scan-error');
            return;
        }

        $this->itemId    = $item->id;
        $this->grnLineId = $line->id;
        $this->gs1Data   = $result->gs1Data;

        // Pre-fill from GS1
        if ($result->gs1Data) {
            $this->qty        = (string) ($result->gs1Data['qty'] ?? '');
            $this->batchNo    = $result->gs1Data['batch'] ?? '';
            $this->expiryDate = $result->gs1Data['expiry'] ?? '';
        }

        $this->step = 'qty';
        $this->dispatch('scan-success');
    }

    private function handleLpnScan(string $raw, BarcodeResolver $resolver): void
    {
        $result = $resolver->resolve($raw, new ScanContext(expects: ScanResultType::Lpn));
        if ($result->type === ScanResultType::Lpn) {
            $this->lpnId  = $result->record->id;
            $this->newLpn = false;
            $this->step   = 'confirm';
            $this->dispatch('scan-success');
        } else {
            $this->dispatch('toast', message: $result->error ?? 'LPN not recognised.', type: 'error');
            $this->dispatch('scan-error');
        }
    }

    public function setQty(string $qty): void
    {
        $this->qty  = $qty;
        $this->step = 'condition';
    }

    public function setCondition(string $condition): void
    {
        $this->condition = $condition;
        $this->step      = 'lpn';
    }

    public function createNewLpn(): void
    {
        $grn         = $this->currentGrn;
        $lpn         = app(LpnService::class)->generate($grn);
        $this->lpnId  = $lpn->id;
        $this->newLpn = true;
        $this->step   = 'confirm';
        $this->dispatch('scan-success');
    }

    public function saveReceipt(): void
    {
        // Idempotency guard
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

        GrnReceipt::create([
            'grn_line_id' => $line->id,
            'qty'         => $this->qty,
            'uom_id'      => $item->base_uom_id,
            'batch_no'    => $this->batchNo ?: null,
            'expiry_date' => $this->expiryDate ?: null,
            'condition'   => $this->condition,
            'lpn_id'      => $this->lpnId,
            'received_by' => auth()->id(),
            'device'      => session('scan_device_code'),
        ]);

        $line->recalculateTotals();

        $this->dispatch('toast', message: "Receipt saved for {$item->sku}.", type: 'success');
        $this->dispatch('scan-success');
        $this->resetForNextItem();
    }

    private function resetForNextItem(): void
    {
        $grnId = $this->grnId;
        $this->reset(['itemId', 'grnLineId', 'qty', 'batchNo', 'expiryDate', 'condition', 'lpnId', 'newLpn', 'gs1Data']);
        $this->grnId     = $grnId;
        $this->requestId = (string) Str::uuid();
        $this->step      = 'item';
    }

    public function back(): void
    {
        $this->step = match ($this->step) {
            'item'      => 'grn',
            'qty'       => 'item',
            'condition' => 'qty',
            'lpn'       => 'condition',
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
