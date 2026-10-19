<?php

declare(strict_types=1);

namespace App\Livewire\Inbound\Grn;

use App\Enums\GrnStatus;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\GrnReceipt;
use App\Models\UnitOfMeasure;
use App\Services\Inbound\GrnWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Tally extends Component
{
    public Grn $grn;

    // Active line being tallied
    public ?int $activeLine = null;

    // Receipt form
    public string $receiptQty       = '';
    public string $receiptUomId     = '';
    public string $receiptBatchNo   = '';
    public string $receiptExpiry    = '';
    public string $receiptCondition = 'good';
    public string $receiptCurrency  = '';
    public string $receiptUnitPrice = '';
    public string $receiptLineTotal = '';
    public string $receiptSerials   = '';

    public function mount(Grn $grn): void
    {
        if ($grn->status !== GrnStatus::Draft || $grn->purchase_order_id === null) {
            $this->redirect(route('inbound.grn.show', $grn), navigate: true);
            return;
        }

        $this->grn = $grn->load(['purchaseOrder', 'receivingDock', 'warehouse']);
        $this->receiptCurrency = $grn->purchaseOrder?->currency ?? 'TZS';
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function lines(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->grn->lines()
            ->with(['item', 'uom', 'receipts.uom'])
            ->get();
    }

    #[Computed]
    public function hasReceipts(): bool
    {
        return $this->grn->receipts()->exists();
    }

    #[Computed]
    public function activeTallyLine(): ?GrnLine
    {
        if ($this->activeLine === null) {
            return null;
        }
        return GrnLine::with('item', 'uom', 'item.uoms.uom')->find($this->activeLine);
    }

    #[Computed]
    public function uomOptions(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->activeTallyLine === null) {
            return collect();
        }
        return UnitOfMeasure::whereIn(
            'id',
            $this->activeTallyLine->item?->uoms->pluck('uom_id') ?? []
        )->get(['id', 'code', 'name']);
    }

    // ─── Lifecycle hooks for auto-total calculation ───────────────────────────

    public function updatedReceiptQty(): void
    {
        $this->recalcTotal();
    }

    public function updatedReceiptUnitPrice(): void
    {
        $this->recalcTotal();
    }

    private function recalcTotal(): void
    {
        $qty   = is_numeric($this->receiptQty) ? (float) $this->receiptQty : 0;
        $price = is_numeric($this->receiptUnitPrice) ? (float) $this->receiptUnitPrice : 0;

        if ($qty > 0 && $price > 0) {
            $this->receiptLineTotal = number_format(round($qty * $price, 4), 4, '.', '');
        } else {
            $this->receiptLineTotal = '';
        }
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function selectLine(int $lineId): void
    {
        if ($this->activeLine === $lineId) {
            $this->activeLine = null;
            return;
        }

        $this->activeLine = $lineId;
        unset($this->activeTallyLine, $this->uomOptions);

        $line = GrnLine::with('uom', 'item.uoms.uom')->find($lineId);

        // Auto-fill UOM from the item's default purchase UOM
        $defaultUomId = $line?->uom_id;
        if ($line?->item) {
            $defaultPurchaseUom = $line->item->uoms
                ->firstWhere('is_default_purchase', true);
            if ($defaultPurchaseUom) {
                $defaultUomId = $defaultPurchaseUom->uom_id;
            }
        }

        // Default currency from PO
        $poCurrency = $this->grn->purchaseOrder?->currency ?? 'TZS';

        // Try to pre-fill unit_price from PO line
        $poLine        = $line?->purchaseOrderLine;
        $defaultPrice  = $poLine?->unit_price ?? null;

        $this->receiptQty       = '';
        $this->receiptUomId     = (string) ($defaultUomId ?? '');
        $this->receiptBatchNo   = '';
        $this->receiptExpiry    = '';
        $this->receiptCondition = 'good';
        $this->receiptCurrency  = $poCurrency;
        $this->receiptUnitPrice = $defaultPrice !== null && (float) $defaultPrice > 0
            ? number_format((float) $defaultPrice, 4, '.', '')
            : '';
        $this->receiptLineTotal = '';
        $this->receiptSerials   = '';
    }

    public function addReceipt(): void
    {
        $this->validate([
            'activeLine'       => ['required', 'exists:grn_lines,id'],
            'receiptQty'       => ['required', 'numeric', 'min:0.0001'],
            'receiptUomId'     => ['required', 'exists:units_of_measure,id'],
            'receiptCondition' => ['required', 'in:good,damaged'],
            'receiptUnitPrice' => ['nullable', 'numeric', 'min:0'],
        ]);

        $line = GrnLine::findOrFail($this->activeLine);

        // Parse and validate serial numbers if item is serial-tracked
        $serialNos = [];
        if ($line->item?->track_serial) {
            $serialNos = array_values(array_filter(
                array_map('trim', preg_split('/[\r\n,]+/', $this->receiptSerials)),
                fn ($s) => $s !== ''
            ));
            $absQty = (int) round((float) $this->receiptQty);
            if (empty($serialNos)) {
                $this->addError('receiptSerials', "Serial numbers are required for this item ({$absQty} needed).");
                return;
            }
            if (count($serialNos) !== $absQty) {
                $this->addError('receiptSerials', "Expected {$absQty} serial number(s), got " . count($serialNos) . '.');
                return;
            }
        }

        $qty   = (string) $this->receiptQty;
        $price = is_numeric($this->receiptUnitPrice) && (float) $this->receiptUnitPrice > 0
            ? (string) $this->receiptUnitPrice
            : null;
        $total = ($price !== null)
            ? (string) round((float) $qty * (float) $price, 4)
            : null;

        GrnReceipt::create([
            'grn_line_id' => $line->id,
            'qty'         => $qty,
            'uom_id'      => (int) $this->receiptUomId,
            'condition'   => $this->receiptCondition,
            'batch_no'    => $this->receiptBatchNo ?: null,
            'expiry_date' => $this->receiptExpiry ?: null,
            'currency'    => $this->receiptCurrency ?: null,
            'unit_price'  => $price,
            'line_total'  => $total,
            'serial_nos'  => ! empty($serialNos) ? $serialNos : null,
            'received_by' => auth()->id(),
        ]);

        $line->recalculateTotals();

        unset($this->lines, $this->activeTallyLine);

        $this->receiptQty       = '';
        $this->receiptBatchNo   = '';
        $this->receiptExpiry    = '';
        $this->receiptCondition = 'good';
        $this->receiptUnitPrice = '';
        $this->receiptLineTotal = '';
        $this->receiptSerials   = '';

        $this->dispatch('toast', message: 'Receipt recorded.', type: 'success');
    }

    public function removeReceipt(int $receiptId): void
    {
        $receipt = GrnReceipt::findOrFail($receiptId);
        $lineId  = $receipt->grn_line_id;
        $receipt->delete();

        $line = GrnLine::find($lineId);
        $line?->recalculateTotals();

        unset($this->lines, $this->activeTallyLine);
        $this->dispatch('toast', message: 'Receipt removed.', type: 'info');
    }

    public function completeReceiving(): void
    {
        $this->authorize('receive', $this->grn->purchaseOrder);

        try {
            app(GrnWorkflow::class)->completeReceiving($this->grn, auth()->user());
            $this->grn->refresh();
            $this->dispatch('toast', message: "GRN {$this->grn->number} complete. Stock posted.", type: 'success');
            $this->redirect(route('inbound.grn.show', $this->grn), navigate: true);
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        return view('livewire.inbound.grn.tally')
            ->layout('components.layouts.app', [
                'title'           => 'Receiving Tally — ' . ($this->grn->purchaseOrder?->number ?? ''),
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
