<?php

namespace App\Livewire\Inbound\Grn;

use App\Enums\GrnStatus;
use App\Enums\ReceiptCondition;
use App\Enums\ScanResultType;
use App\Livewire\Concerns\HandlesScans;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\GrnReceipt;
use App\Models\Inventory\Lpn;
use App\Models\ReasonCode;
use App\Models\UnitOfMeasure;
use App\Enums\LabelTemplateType;
use App\Services\Foundation\SettingsService;
use App\Services\Inbound\GrnWorkflow;
use App\Services\Inbound\LpnService;
use App\Services\Scanning\ScanResult;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Receive extends Component
{
    use HandlesScans;

    public Grn $grn;

    // Active line for receipt entry
    public ?int $activeLine = null;

    // Receipt form
    public string $receiptQty       = '';
    public string $receiptUomId     = '';
    public string $receiptBatchNo   = '';
    public string $receiptExpiry    = '';
    public string $receiptCondition      = 'good';
    public string $receiptLpnId          = '';
    public string $receiptSerials        = '';  // one serial per line, for track_serial items
    public bool   $receiptSerialsMissing = false;

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(Grn $grn): void
    {
        $this->grn = $grn;

        if ($grn->status === GrnStatus::Draft) {
            app(GrnWorkflow::class)->startReceiving($grn, auth()->user());
            $this->grn->refresh();
        }

        if (! in_array($grn->status, [GrnStatus::Receiving, GrnStatus::PendingVarianceApproval], true)) {
            $this->redirect(route('inbound.grn.show', $grn), navigate: true);
        }
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function lines(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->grn->lines()
            ->with(['item', 'uom', 'receipts'])
            ->get();
    }

    #[Computed]
    public function activeGrnLine(): ?GrnLine
    {
        if ($this->activeLine === null) {
            return null;
        }
        return GrnLine::with('item.uoms.uom', 'uom')->find($this->activeLine);
    }

    #[Computed]
    public function uomOptions(): \Illuminate\Support\Collection
    {
        if ($this->activeGrnLine === null) {
            return collect();
        }

        $item = $this->activeGrnLine->item;
        $uoms = collect();

        if ($item) {
            $baseUom = $item->baseUom;
            if ($baseUom) {
                $uoms->push($baseUom);
            }
            foreach ($item->uoms as $itemUom) {
                if ($itemUom->uom && ! $uoms->contains('id', $itemUom->uom->id)) {
                    $uoms->push($itemUom->uom);
                }
            }
        }

        return $uoms;
    }

    #[Computed]
    public function conditions(): array
    {
        return ReceiptCondition::cases();
    }

    #[Computed]
    public function grnLpns(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->grn->lpns;
    }

    // ─── Scan Handling ────────────────────────────────────────────────────────

    public function processScan(): void
    {
        $result = $this->resolveScan(null, $this->grn->warehouse_id);
        if ($result === null) return;

        if (! $result->ok()) {
            $this->scanFail($result->error ?? 'Barcode not recognised.');
            return;
        }

        // GS1 data → prefill qty / batch / expiry from barcode
        if ($result->gs1Data) {
            $gs1 = $result->gs1Data;
            if (! empty($gs1['qty']))    $this->receiptQty     = (string) $gs1['qty'];
            if (! empty($gs1['batch']))  $this->receiptBatchNo = $gs1['batch'];
            if (! empty($gs1['expiry'])) $this->receiptExpiry  = $gs1['expiry'];
        }

        match ($result->type) {
            ScanResultType::Item => $this->handleItemScan($result),
            ScanResultType::Lpn  => $this->handleLpnScan($result),
            default              => $this->scanFail('Scan not applicable here.'),
        };
    }

    private function handleItemScan(ScanResult $result): void
    {
        $item = $result->record;
        $line = $this->lines->first(fn ($l) => $l->item_id === $item?->getKey());
        if ($line) {
            $this->selectLine($line->id);
            $this->scanOk("Line selected: {$item?->name}");
        } else {
            $this->scanFail("Item {$item?->sku} not on this GRN.");
        }
    }

    private function handleLpnScan(ScanResult $result): void
    {
        $lpn = $result->record;
        if ($lpn) {
            $this->receiptLpnId = (string) $lpn->getKey();
            $this->scanOk("LPN set: {$lpn->code}");
        } else {
            $this->scanFail('LPN not found.');
        }
    }

    public function selectLine(int $lineId): void
    {
        $this->activeLine       = $lineId;
        $this->receiptQty       = '';
        $this->receiptBatchNo   = '';
        $this->receiptExpiry    = '';
        $this->receiptCondition = 'good';
        $this->receiptLpnId     = '';

        // Pre-fill UoM from line
        $line = GrnLine::find($lineId);
        $this->receiptUomId = (string) ($line?->uom_id ?? '');
    }

    // ─── Receipt Management ───────────────────────────────────────────────────

    public function addReceipt(): void
    {
        $item        = $this->activeGrnLine?->item;
        $trackSerial = (bool) ($item?->track_serial);
        $trackBatch  = (bool) ($item?->track_batch);
        $trackExpiry = (bool) ($item?->track_expiry);

        $serialsRequired = $trackSerial && ! $this->receiptSerialsMissing;

        $this->validate([
            'activeLine'       => ['required', 'exists:grn_lines,id'],
            'receiptQty'       => ['required', 'numeric', 'min:0.0001'],
            'receiptUomId'     => ['required', 'exists:units_of_measure,id'],
            'receiptBatchNo'   => $trackBatch     ? ['required', 'string', 'max:100'] : ['nullable', 'string', 'max:100'],
            'receiptExpiry'    => $trackExpiry    ? ['required', 'date']              : ['nullable', 'date'],
            'receiptCondition' => ['required', 'in:good,damaged'],
            'receiptLpnId'     => ['nullable', 'exists:lpns,id'],
            'receiptSerials'   => $serialsRequired ? ['required', 'string']           : ['nullable'],
        ]);

        // Parse serials — one per line, deduplicated, non-empty
        $serialNos = null;
        if ($trackSerial && ! $this->receiptSerialsMissing) {
            $parsed = array_values(array_unique(array_filter(
                array_map('trim', preg_split('/[\r\n,]+/', $this->receiptSerials)),
                fn ($s) => $s !== ''
            )));

            $qty = (int) round((float) $this->receiptQty);
            if (count($parsed) !== $qty) {
                $this->addError('receiptSerials', "Expected {$qty} serial(s), got " . count($parsed) . '.');
                return;
            }
            $serialNos = $parsed;
        }

        GrnReceipt::create([
            'grn_line_id' => $this->activeLine,
            'qty'         => $this->receiptQty,
            'uom_id'      => (int) $this->receiptUomId,
            'batch_no'    => $this->receiptBatchNo ?: null,
            'expiry_date' => $this->receiptExpiry ?: null,
            'condition'   => $this->receiptCondition,
            'lpn_id'      => $this->receiptLpnId ?: null,
            'serial_nos'  => $serialNos,
            'received_by' => auth()->id(),
        ]);

        GrnLine::find($this->activeLine)?->recalculateTotals();

        $this->receiptQty           = '';
        $this->receiptBatchNo       = '';
        $this->receiptExpiry        = '';
        $this->receiptLpnId         = '';
        $this->receiptSerials       = '';
        $this->receiptSerialsMissing = false;

        $this->dispatch('toast', message: 'Receipt recorded.', type: 'success');
    }

    public function deleteReceipt(int $receiptId): void
    {
        $receipt = GrnReceipt::find($receiptId);
        if (! $receipt) {
            return;
        }

        $lineId = $receipt->grn_line_id;
        $receipt->delete();

        GrnLine::find($lineId)?->recalculateTotals();
        $this->dispatch('toast', message: 'Receipt deleted.', type: 'info');
    }

    public function generateLpn(): void
    {
        $lpn = app(LpnService::class)->generate($this->grn);
        $this->receiptLpnId = (string) $lpn->id;
        $this->dispatch('toast', message: "LPN generated: {$lpn->code}", type: 'success');

        if (SettingsService::get('labels.auto_print_lpn', true)) {
            $this->dispatch('open-label-print',
                type: LabelTemplateType::PalletLpn->value,
                ids:  [$lpn->id],
            );
        }
    }

    // ─── Finish Receiving ─────────────────────────────────────────────────────

    public function finishReceiving(): void
    {
        $this->authorize('confirm', $this->grn);

        try {
            app(GrnWorkflow::class)->finishReceiving($this->grn, auth()->user());
            $this->dispatch('toast', message: 'Receiving complete — click Confirm GRN to post stock.', type: 'success');
            $this->redirect(route('inbound.grn.show', $this->grn), navigate: true);
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Error: ' . $e->getMessage(), type: 'error');
        }
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.inbound.grn.receive')
            ->layout('components.layouts.app', [
                'title'           => 'Receive: ' . $this->grn->number,
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
