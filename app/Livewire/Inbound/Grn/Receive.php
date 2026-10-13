<?php

namespace App\Livewire\Inbound\Grn;

use App\Enums\GrnStatus;
use App\Enums\ReceiptCondition;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\GrnReceipt;
use App\Models\Inventory\Lpn;
use App\Models\ReasonCode;
use App\Models\UnitOfMeasure;
use App\Services\Inbound\GrnWorkflow;
use App\Services\Inbound\LpnService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Receive extends Component
{
    public Grn $grn;

    // Scan bar
    public string $scanInput = '';

    // Active line for receipt entry
    public ?int $activeLine = null;

    // Receipt form
    public string $receiptQty       = '';
    public string $receiptUomId     = '';
    public string $receiptBatchNo   = '';
    public string $receiptExpiry    = '';
    public string $receiptCondition = 'good';
    public string $receiptLpnId     = '';

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
    public function uomOptions(): \Illuminate\Database\Eloquent\Collection
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
        $input = trim($this->scanInput);
        $this->scanInput = '';

        if ($input === '') {
            return;
        }

        // Try matching to an item SKU or barcode
        $line = $this->lines->first(function ($l) use ($input) {
            return $l->item
                && (
                    strtoupper($l->item->sku) === strtoupper($input)
                    || $l->item->barcodes()->where('barcode', $input)->exists()
                );
        });

        if ($line) {
            $this->selectLine($line->id);
            $this->dispatch('toast', message: "Line found: {$line->item->name}", type: 'info');
        } else {
            $this->dispatch('toast', message: "No matching line for: {$input}", type: 'error');
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
        $this->validate([
            'activeLine'       => ['required', 'exists:grn_lines,id'],
            'receiptQty'       => ['required', 'numeric', 'min:0.0001'],
            'receiptUomId'     => ['required', 'exists:units_of_measure,id'],
            'receiptBatchNo'   => ['nullable', 'string', 'max:100'],
            'receiptExpiry'    => ['nullable', 'date'],
            'receiptCondition' => ['required', 'in:good,damaged'],
            'receiptLpnId'     => ['nullable', 'exists:lpns,id'],
        ]);

        GrnReceipt::create([
            'grn_line_id' => $this->activeLine,
            'qty'         => $this->receiptQty,
            'uom_id'      => (int) $this->receiptUomId,
            'batch_no'    => $this->receiptBatchNo ?: null,
            'expiry_date' => $this->receiptExpiry ?: null,
            'condition'   => $this->receiptCondition,
            'lpn_id'      => $this->receiptLpnId ?: null,
            'received_by' => auth()->id(),
        ]);

        // Recalculate line totals
        GrnLine::find($this->activeLine)?->recalculateTotals();

        $this->receiptQty     = '';
        $this->receiptBatchNo = '';
        $this->receiptExpiry  = '';
        $this->receiptLpnId   = '';

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
    }

    // ─── Finish Receiving ─────────────────────────────────────────────────────

    public function finishReceiving(): void
    {
        $this->authorize('confirm', $this->grn);

        try {
            app(GrnWorkflow::class)->finishReceiving($this->grn, auth()->user());
            $this->dispatch('toast', message: 'Receiving finished — stock posted.', type: 'success');
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
