<?php

declare(strict_types=1);

namespace App\Livewire\Inbound\Grn;

use App\Enums\GrnStatus;
use App\Enums\ScanResultType;
use App\Models\Grn;
use App\Models\GrnLine;
use App\Models\GrnReceipt;
use App\Models\UnitOfMeasure;
use App\Services\Foundation\UomConverter;
use App\Services\Inbound\GrnWorkflow;
use App\Services\Scanning\BarcodeResolver;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Tally extends Component
{
    public Grn $grn;

    // Scan bar
    public string $scanInput = '';

    // Active line being tallied
    public ?int   $activeLine    = null;

    // Multi-line picker (when scan matches more than one GRN line)
    public array  $multiMatchLines = [];

    // Receipt form
    public string $receiptQty          = '';
    public string $receiptUomId        = '';
    public string $receiptBatchNo      = '';
    public string $receiptExpiry       = '';
    public string $receiptCondition    = 'good';
    public string $receiptDamageRemark = '';
    public string $receiptSerials      = '';
    public string $receiptLpnId        = '';

    public function mount(Grn $grn): void
    {
        if ($grn->status !== GrnStatus::Draft || $grn->purchase_order_id === null) {
            $this->redirect(route('inbound.grn.show', $grn), navigate: true);
            return;
        }

        $this->grn = $grn->load(['purchaseOrder', 'receivingDock', 'warehouse']);
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
        return GrnLine::with('item', 'uom', 'item.uoms.uom', 'receipts.uom')->find($this->activeLine);
    }

    #[Computed]
    public function itemRequiresExpiry(): bool
    {
        $item = $this->activeTallyLine?->item;
        if (! $item) {
            return false;
        }

        return $item->track_expiry
            || in_array('fefo', $item->allocation_rules ?? [], true);
    }

    #[Computed]
    public function uomOptions(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->activeTallyLine === null) {
            return collect();
        }

        $item = $this->activeTallyLine->item;

        // Prefer the item's configured UoMs from item_uoms
        $ids = $item?->uoms->pluck('uom_id')->filter()->all() ?? [];
        if (! empty($ids)) {
            return UnitOfMeasure::whereIn('id', $ids)->get(['id', 'code', 'name']);
        }

        // Fall back to the item's base UoM
        if ($item?->base_uom_id) {
            return UnitOfMeasure::where('id', $item->base_uom_id)->get(['id', 'code', 'name']);
        }

        // Last resort: all UoMs
        return UnitOfMeasure::orderBy('code')->get(['id', 'code', 'name']);
    }

    // ─── Scan ─────────────────────────────────────────────────────────────────

    public function processScan(): void
    {
        $input          = trim($this->scanInput);
        $this->scanInput = '';
        $this->multiMatchLines = [];

        if ($input === '') {
            return;
        }

        $result = app(BarcodeResolver::class)->resolve($input);

        if ($result->type === ScanResultType::Lpn) {
            $this->receiptLpnId = (string) $result->record->id;
            $this->dispatch('scan-success');
            $this->dispatch('toast', message: "LPN: {$result->record->code}", type: 'info');
            return;
        }

        if ($result->type !== ScanResultType::Item) {
            $this->dispatch('scan-error');
            $this->dispatch('toast', message: $result->error ?? 'Barcode not recognised.', type: 'error');
            return;
        }

        $item          = $result->record;
        $matchingLines = $this->lines->filter(fn ($l) => $l->item_id === $item->id)->values();

        if ($matchingLines->isEmpty()) {
            $this->dispatch('scan-error');
            $this->dispatch('toast', message: "Item {$item->sku} is not on this GRN.", type: 'error');
            return;
        }

        if ($matchingLines->count() > 1) {
            $this->multiMatchLines = $matchingLines->map(fn ($l) => [
                'id'       => $l->id,
                'sku'      => $l->item?->sku,
                'name'     => $l->item?->name,
                'expected' => (float) $l->expected_qty,
                'received' => (float) bcadd((string) $l->received_good_qty, (string) $l->received_damaged_qty, 4),
            ])->toArray();
            $this->dispatch('scan-success');
            return;
        }

        $line = $matchingLines->first();

        // Same item scanned while line is already active → increment qty by 1
        if ($this->activeLine === $line->id) {
            $this->receiptQty = (string) ((is_numeric($this->receiptQty) ? (float) $this->receiptQty : 0) + 1);
            $this->dispatch('scan-success');
            return;
        }

        $this->selectLine($line->id);

        // Fill GS1 fields if present
        if ($result->gs1Data) {
            if (! empty($result->gs1Data['batch']))  $this->receiptBatchNo = $result->gs1Data['batch'];
            if (! empty($result->gs1Data['expiry'])) $this->receiptExpiry  = $result->gs1Data['expiry'];
            if (! empty($result->gs1Data['qty']))    $this->receiptQty     = (string) $result->gs1Data['qty'];
        }

        $this->dispatch('scan-success');
    }

    public function selectFromMulti(int $lineId): void
    {
        $this->multiMatchLines = [];
        $this->selectLine($lineId);
        $this->dispatch('scan-success');
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

        // Default to the PO line's UoM (the UoM the order was placed in)
        $defaultUomId = $line?->uom_id ?? $line?->item?->base_uom_id;

        $this->receiptQty          = '';
        $this->receiptUomId        = (string) ($defaultUomId ?? '');
        $this->receiptBatchNo      = '';
        $this->receiptExpiry       = '';
        $this->receiptCondition    = 'good';
        $this->receiptDamageRemark = '';
        $this->receiptSerials      = '';
        $this->receiptLpnId        = '';
    }

    public function addReceipt(): void
    {
        $this->validate([
            'activeLine'          => ['required', 'exists:grn_lines,id'],
            'receiptQty'          => ['required', 'numeric', 'min:0.0001'],
            'receiptUomId'        => ['required', 'exists:units_of_measure,id'],
            'receiptCondition'    => ['required', 'in:good,damaged'],
            'receiptDamageRemark' => ['required_if:receiptCondition,damaged', 'nullable', 'string', 'max:500'],
        ]);

        $line = GrnLine::findOrFail($this->activeLine);

        // Over-receipt guard: total received + this qty must not exceed expected
        $alreadyReceived = bcadd(
            (string) ($line->received_good_qty ?? '0'),
            (string) ($line->received_damaged_qty ?? '0'),
            4
        );
        $newTotal = bcadd($alreadyReceived, (string) $this->receiptQty, 4);
        $expected = (string) ($line->expected_qty ?? '0');

        if (bccomp($expected, '0', 4) > 0 && bccomp($newTotal, $expected, 4) > 0) {
            $remaining = bcsub($expected, $alreadyReceived, 4);
            $this->addError('receiptQty', "Over-receipt blocked. Expected {$expected}, already received {$alreadyReceived}. Max remaining: {$remaining}.");
            return;
        }

        // Enforce expiry date for FEFO / expiry-tracked items
        if ($this->itemRequiresExpiry && trim($this->receiptExpiry) === '') {
            $this->addError('receiptExpiry', 'Expiry date is required for this item (FEFO / expiry-tracked).');
            return;
        }

        // Parse and validate serial numbers if item is serial-tracked
        $serialNos = [];
        if ($line->item?->track_serial) {
            $serialNos = array_values(array_filter(
                array_map('trim', preg_split('/[\r\n,]+/', $this->receiptSerials)),
                fn ($s) => $s !== ''
            ));

            // Serials must match BASE qty (after UoM conversion), not entered qty
            $baseQty = (int) round((float) UomConverter::toBase(
                $line->item,
                (int) $this->receiptUomId,
                $this->receiptQty,
            ));

            if (empty($serialNos)) {
                $this->addError('receiptSerials', "Serial numbers are required ({$baseQty} needed).");
                return;
            }
            if (count($serialNos) !== $baseQty) {
                $this->addError('receiptSerials', "Expected {$baseQty} serial number(s) for this quantity, got " . count($serialNos) . '.');
                return;
            }
        }

        GrnReceipt::create([
            'grn_line_id'   => $line->id,
            'qty'           => (string) $this->receiptQty,
            'uom_id'        => (int) $this->receiptUomId,
            'condition'     => $this->receiptCondition,
            'damage_remark' => $this->receiptCondition === 'damaged' ? ($this->receiptDamageRemark ?: null) : null,
            'batch_no'      => $this->receiptBatchNo ?: null,
            'expiry_date'   => $this->receiptExpiry ?: null,
            'serial_nos'    => ! empty($serialNos) ? $serialNos : null,
            'lpn_id'        => $this->receiptLpnId ? (int) $this->receiptLpnId : null,
            'received_by'   => auth()->id(),
        ]);

        $line->recalculateTotals();

        unset($this->lines, $this->activeTallyLine);

        $this->receiptQty          = '';
        $this->receiptBatchNo      = '';
        $this->receiptExpiry       = '';
        $this->receiptCondition    = 'good';
        $this->receiptDamageRemark = '';
        $this->receiptSerials      = '';
        $this->receiptLpnId        = '';

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
                'title'           => 'Goods Receipt Entry — ' . ($this->grn->purchaseOrder?->number ?? ''),
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
