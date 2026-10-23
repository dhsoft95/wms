<?php

declare(strict_types=1);

namespace App\Livewire\Inbound\Orders;

use App\Enums\Currency;
use App\Enums\Inbound\PackageType;
use App\Enums\Inbound\PurchaseOrderStatus;
use App\Models\Department;
use App\Models\Item;
use App\Models\Partner;
use App\Models\PurchaseOrder;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use WithFileUploads;

    public PurchaseOrder $purchaseOrder;

    public string $virtualWarehouseId  = '';
    public string $warehouseId         = '';
    public string $departmentId        = '';
    public string $packageType         = 'loose_cargo';
    public string $customerPoRef       = '';
    public string $supplierPartnerId   = '';
    public string $consigneePartnerId  = '';
    public string $currency            = 'TZS';
    public string $expectedDate        = '';
    public string $notes               = '';
    public mixed  $poFile              = null;
    public bool   $removeDoc           = false;

    public bool   $isSpecialTreatment     = false;
    public string $specialTreatmentReason = '';

    public array $lines = [];

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        if (! $purchaseOrder->status->isEditable()) {
            abort(403, 'This purchase order cannot be edited in its current status.');
        }

        $this->purchaseOrder = $purchaseOrder->load('lines');

        $this->warehouseId        = (string) ($purchaseOrder->warehouse_id ?? '');
        $this->virtualWarehouseId = (string) ($purchaseOrder->virtual_warehouse_id ?? '');
        $this->departmentId       = (string) ($purchaseOrder->department_id ?? '');
        $this->packageType        = $purchaseOrder->package_type?->value ?? 'loose_cargo';
        $this->customerPoRef      = $purchaseOrder->customer_po_ref ?? '';
        $this->supplierPartnerId  = (string) ($purchaseOrder->supplier_partner_id ?? '');
        $this->consigneePartnerId = (string) ($purchaseOrder->consignee_partner_id ?? '');
        $this->currency           = $purchaseOrder->currency ?? 'TZS';
        $this->expectedDate           = $purchaseOrder->expected_date?->format('Y-m-d') ?? '';
        $this->isSpecialTreatment     = (bool) ($purchaseOrder->is_special_treatment ?? false);
        $this->specialTreatmentReason = $purchaseOrder->special_treatment_reason ?? '';
        $this->notes                  = $purchaseOrder->notes ?? '';

        $this->lines = $purchaseOrder->lines->map(fn ($l) => [
            'item_id'     => (string) $l->item_id,
            'uom_id'      => (string) $l->uom_id,
            'part_number' => $l->part_number ?? '',
            'qty'         => (string) $l->qty,
            'currency'    => $l->currency ?? $purchaseOrder->currency ?? 'TZS',
            'unit_price'  => (string) ($l->unit_price ?? ''),
            'line_total'  => $l->line_total ? number_format((float) $l->line_total, 2, '.', '') : '',
            'batch_no'    => $l->batch_no ?? '',
            'expiry_date' => $l->expiry_date?->format('Y-m-d') ?? '',
            'notes'       => $l->notes ?? '',
        ])->toArray();

        if (empty($this->lines)) {
            $this->lines = [$this->blankLine()];
        }
    }

    private function blankLine(string $currency = ''): array
    {
        return [
            'item_id'     => '',
            'uom_id'      => '',
            'part_number' => '',
            'qty'         => '',
            'currency'    => $currency,
            'unit_price'  => '',
            'line_total'  => '',
            'batch_no'    => '',
            'expiry_date' => '',
            'notes'       => '',
        ];
    }

    public function addLine(): void
    {
        $this->lines[] = $this->blankLine($this->currency ?: 'TZS');
    }

    public function removeLine(int $idx): void
    {
        if (count($this->lines) > 1) {
            array_splice($this->lines, $idx, 1);
        }
    }

    #[Computed]
    public function virtualWarehouses()
    {
        return VirtualWarehouse::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function warehouses()
    {
        return Warehouse::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function departments()
    {
        return Department::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function partners()
    {
        return Partner::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function packageTypes()
    {
        return PackageType::cases();
    }

    #[Computed]
    public function currencies()
    {
        return Currency::cases();
    }

    #[Computed]
    public function items()
    {
        return Item::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'part_number']);
    }

    #[Computed]
    public function uoms()
    {
        return UnitOfMeasure::orderBy('code')->get(['id', 'code', 'name']);
    }

    public function updatedLines($value, $key): void
    {
        $parts = explode('.', (string) $key);
        $idx   = (int) ($parts[0] ?? 0);
        $field = $parts[1] ?? '';

        if ($field === 'item_id' && ! empty($value)) {
            $item = Item::with('uoms')->find((int) $value);
            if ($item) {
                $defaultUom = $item->uoms->firstWhere('is_default_purchase', true);
                $uomId      = $defaultUom?->uom_id ?? $item->base_uom_id;
                if ($uomId) {
                    $this->lines[$idx]['uom_id'] = (string) $uomId;
                }
            }
            return;
        }

        if ($field === 'qty' || $field === 'unit_price') {
            $qty   = is_numeric($this->lines[$idx]['qty'] ?? '') ? (float) $this->lines[$idx]['qty'] : 0;
            $price = is_numeric($this->lines[$idx]['unit_price'] ?? '') ? (float) $this->lines[$idx]['unit_price'] : 0;
            $this->lines[$idx]['line_total'] = ($qty > 0 && $price > 0)
                ? number_format($qty * $price, 2, '.', '')
                : '';
        }
    }

    public function save(): void
    {
        $rules = [
            'warehouseId'            => ['required', 'exists:warehouses,id'],
            'packageType'            => ['required', 'in:container,loose_cargo'],
            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.item_id'        => ['required', 'exists:items,id'],
            'lines.*.uom_id'         => ['required', 'exists:units_of_measure,id'],
            'lines.*.qty'            => ['required', 'numeric', 'min:0.0001'],
            'poFile'                 => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:20480'],
            'specialTreatmentReason' => $this->isSpecialTreatment ? ['required', 'string', 'min:10'] : ['nullable'],
        ];

        $this->validate($rules);

        // Handle document replacement / removal
        $poFilePath = $this->purchaseOrder->po_file_path;

        if ($this->removeDoc) {
            if ($poFilePath) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($poFilePath);
            }
            $poFilePath = null;
        }

        if ($this->poFile) {
            // Delete old file before storing the new one
            if ($poFilePath) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($poFilePath);
            }
            $ext        = $this->poFile->getClientOriginalExtension();
            $poFilePath = $this->poFile->storeAs(
                'po-documents',
                now()->format('Ymd_His') . '_' . \Illuminate\Support\Str::random(6) . '.' . $ext,
                'local'
            );
        }

        $this->purchaseOrder->update([
            'virtual_warehouse_id'     => $this->virtualWarehouseId ?: null,
            'warehouse_id'             => $this->warehouseId,
            'department_id'            => $this->departmentId ?: null,
            'package_type'             => $this->packageType,
            'customer_po_ref'          => $this->customerPoRef ?: null,
            'supplier_partner_id'      => $this->supplierPartnerId ?: null,
            'consignee_partner_id'     => $this->consigneePartnerId ?: null,
            'currency'                 => $this->currency ?: 'TZS',
            'expected_date'            => $this->expectedDate ?: null,
            'is_special_treatment'     => $this->isSpecialTreatment,
            'special_treatment_reason' => $this->isSpecialTreatment ? $this->specialTreatmentReason : null,
            'notes'                    => $this->notes ?: null,
            'po_file_path'             => $poFilePath,
        ]);

        // Replace lines: delete old, create new
        $this->purchaseOrder->lines()->delete();
        foreach ($this->lines as $i => $line) {
            $price = is_numeric($line['unit_price'] ?? '') && (float) ($line['unit_price'] ?? 0) > 0
                ? $line['unit_price']
                : '0';
            $qty = (float) ($line['qty'] ?? 0);
            $this->purchaseOrder->lines()->create([
                'line_no'     => $i + 1,
                'item_id'     => $line['item_id'],
                'uom_id'      => $line['uom_id'],
                'part_number' => $line['part_number'] ?: null,
                'qty'         => $line['qty'],
                'qty_base'    => $line['qty'],
                'currency'    => $line['currency'] ?: null,
                'unit_price'  => $price,
                'line_total'  => $qty > 0 && (float) $price > 0 ? number_format($qty * (float) $price, 4, '.', '') : null,
                'batch_no'    => $line['batch_no'] ?: null,
                'expiry_date' => $line['expiry_date'] ?: null,
                'notes'       => $line['notes'] ?: null,
            ]);
        }

        $this->dispatch('toast', message: 'Purchase order updated.', type: 'success');
        $this->redirect(route('inbound.orders.show', $this->purchaseOrder), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.inbound.orders.edit')
            ->layout('components.layouts.app', [
                'title'           => "Edit {$this->purchaseOrder->number}",
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
