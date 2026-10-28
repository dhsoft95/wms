<?php

declare(strict_types=1);

namespace App\Livewire\Inbound\Orders;

use App\Enums\Currency;
use App\Enums\Inbound\PackageType;
use App\Livewire\Concerns\WithBulkLineUpload;
use App\Models\Department;
use App\Models\Item;
use App\Models\Partner;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads, WithBulkLineUpload;

    public string $virtualWarehouseId  = '';
    public string $warehouseId         = '';
    public string $departmentId        = '';
    public string $packageType         = 'loose_cargo';
    public string $source              = 'manual';
    public string $customerPoRef       = '';
    public string $supplierPartnerId   = '';
    public string $consigneePartnerId  = '';
    public string $currency            = 'TZS';
    public string $expectedDate        = '';
    public string $notes               = '';
    public mixed  $poFile              = null;

    public bool   $isSpecialTreatment     = false;
    public string $specialTreatmentReason = '';

    public array $lines = [];

    public static function lineFields(): array
    {
        return [
            ['key' => 'item_id',     'column' => 'Item (SKU)',  'type' => 'sku',     'required' => true,  'format' => 'Item SKU code',      'example' => 'SKU-001'],
            ['key' => 'uom_id',      'column' => 'Unit',        'type' => 'uom',     'required' => true,  'format' => 'UoM code (e.g. EA)', 'example' => 'EA'],
            ['key' => 'qty',         'column' => 'Quantity',    'type' => 'decimal', 'required' => true,  'format' => 'Number > 0',         'example' => '100'],
            ['key' => 'batch_no',    'column' => 'Batch No',    'type' => 'text',    'required' => false, 'format' => 'Text, max 100',      'example' => 'BATCH-2024'],
            ['key' => 'expiry_date', 'column' => 'Expiry Date', 'type' => 'date',    'required' => false, 'format' => 'YYYY-MM-DD',         'example' => '2025-12-31'],
            ['key' => 'notes',       'column' => 'Notes',       'type' => 'text',    'required' => false, 'format' => 'Free text',          'example' => ''],
        ];
    }

    protected function bulkContext(): array
    {
        return [];
    }

    protected function applyBulkLines(array $rows): void
    {
        if ($this->blkMode === 'replace') {
            $this->lines = [];
        }

        foreach ($rows as $row) {
            $this->lines[] = [
                'item_id'     => $row['item_id']    ?? '',
                'uom_id'      => $row['uom_id']     ?? '',
                'qty'         => $row['qty']         ?? '',
                'unit_price'  => $row['unit_price']  ?? '',
                'batch_no'    => $row['batch_no']    ?? '',
                'expiry_date' => $row['expiry_date'] ?? '',
                'notes'       => $row['notes']       ?? '',
            ];
        }

        if (empty($this->lines)) {
            $this->lines = [$this->blankLine()];
        }
    }

    public function mount(): void
    {
        $this->lines = [$this->blankLine($this->currency ?: 'TZS')];

        // Pre-fill department for department users
        $user = auth()->user();
        if ($user->hasRole('department-user') && $user->department_id) {
            $this->departmentId = (string) $user->department_id;
        }
    }

    private function blankLine(string $currency = ''): array
    {
        return [
            'item_id'     => '',
            'uom_id'      => '',
            'part_number' => '',
            'mic_number'  => '',
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

    public function updatedLines($value, $key): void
    {
        $parts = explode('.', (string) $key);
        $idx   = (int) ($parts[0] ?? 0);
        $field = $parts[1] ?? '';

        if ($field === 'item_id' && ! empty($value)) {
            $item = Item::with('uoms')->find((int) $value);
            if ($item) {
                // Default purchase UOM, fall back to item's base UOM
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

        $linesData = array_map(function ($line) {
            return [
                'item_id'     => $line['item_id'],
                'uom_id'      => $line['uom_id'],
                'part_number' => $line['part_number'] ?: null,
                'mic_number'  => $line['mic_number'] ?: null,
                'qty'         => $line['qty'],
                'qty_base'    => $line['qty'],
                'currency'    => $line['currency'] ?: null,
                'unit_price'  => $line['unit_price'] ?: '0',
                'line_total'  => $line['line_total'] ?: null,
                'batch_no'    => $line['batch_no'] ?: null,
                'expiry_date' => $line['expiry_date'] ?: null,
                'notes'       => $line['notes'] ?: null,
            ];
        }, $this->lines);

        $poFilePath = null;
        if ($this->poFile) {
            $ext        = $this->poFile->getClientOriginalExtension();
            $poFilePath = $this->poFile->storeAs(
                'po-documents',
                now()->format('Ymd_His') . '_' . \Illuminate\Support\Str::random(6) . '.' . $ext,
                'local'
            );
        }

        $data = [
            'virtual_warehouse_id'     => $this->virtualWarehouseId ?: null,
            'warehouse_id'             => $this->warehouseId,
            'department_id'            => $this->departmentId ?: null,
            'package_type'             => $this->packageType,
            'source'                   => 'manual',
            'customer_po_ref'          => $this->customerPoRef ?: null,
            'supplier_partner_id'      => $this->supplierPartnerId ?: null,
            'consignee_partner_id'     => $this->consigneePartnerId ?: null,
            'currency'                 => $this->currency ?: 'TZS',
            'expected_date'            => $this->expectedDate ?: null,
            'is_special_treatment'     => $this->isSpecialTreatment,
            'special_treatment_reason' => $this->isSpecialTreatment ? $this->specialTreatmentReason : null,
            'notes'                    => $this->notes ?: null,
            'po_file_path'             => $poFilePath,
            'lines'                    => $linesData,
        ];

        $po = app(\App\Services\Inbound\PurchaseOrderWorkflow::class)->create($data, auth()->user());

        $this->dispatch('toast', message: "PO {$po->number} created.", type: 'success');
        $this->redirect(route('inbound.orders.show', $po), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.inbound.orders.create')
            ->layout('components.layouts.app', [
                'title'           => 'New Purchase Order',
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
