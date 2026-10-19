<?php

namespace App\Livewire\Admin\Items;

use App\Actions\Foundation\UpdateItemAction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use App\Rules\NoConflictingAllocationRules;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Edit extends Component
{
    public Item $item;

    public string $activeTab = 'general';

    // General tab
    public string $sku = '';
    public string $name = '';
    public string $description = '';
    public string $categoryId = '';
    public string $baseUomId = '';
    public string $defaultVwId = '';
    public bool   $isActive = true;

    // Dimensions
    public string $length = '';
    public string $width = '';
    public string $height = '';
    public string $weight = '';

    // UoMs tab
    public array $uoms = [];

    // Barcodes tab
    public array $barcodes = [];

    // Tracking tab
    public bool $trackBatch  = false;
    public bool $trackExpiry = false;
    public bool $trackSerial = false;
    public string $preferredZoneId = '';

    // Stock levels tab
    public string $minStock = '';
    public string $maxStock = '';

    // Rules & Approvals tab
    public array  $allocationRules          = [];
    public string $varianceThresholdPct     = '';
    public string $varianceThresholdQty     = '';
    public bool   $dispatchRequiresApproval = true;
    public string $dispatchApprovalQty      = '';
    public string $adjustmentApproval       = 'inherit';

    // ─── Mount ────────────────────────────────────────────────────────────────

    public function mount(Item $item): void
    {
        $this->item = $item->load(['uoms.uom', 'barcodes.uom']);

        $this->sku         = $item->sku;
        $this->name        = $item->name;
        $this->description = $item->description ?? '';
        $this->categoryId  = (string) ($item->category_id ?? '');
        $this->baseUomId   = (string) ($item->base_uom_id ?? '');
        $this->defaultVwId = (string) ($item->default_virtual_warehouse_id ?? '');
        $this->isActive    = (bool) $item->is_active;

        $this->length = (string) ($item->length ?? '');
        $this->width  = (string) ($item->width ?? '');
        $this->height = (string) ($item->height ?? '');
        $this->weight = (string) ($item->weight ?? '');

        $this->trackBatch      = (bool) $item->track_batch;
        $this->trackExpiry     = (bool) $item->track_expiry;
        $this->trackSerial     = (bool) $item->track_serial;
        $this->preferredZoneId = (string) ($item->preferred_zone_id ?? '');

        $this->minStock = (string) ($item->min_stock ?? '');
        $this->maxStock = (string) ($item->max_stock ?? '');

        $this->allocationRules          = $item->allocation_rules ?? [];
        $this->varianceThresholdPct     = (string) ($item->variance_threshold_pct ?? '');
        $this->varianceThresholdQty     = (string) ($item->variance_threshold_qty ?? '');
        $this->dispatchRequiresApproval = (bool) $item->dispatch_requires_approval;
        $this->dispatchApprovalQty      = (string) ($item->dispatch_approval_qty ?? '');
        $this->adjustmentApproval       = $item->adjustment_approval ?? 'inherit';

        // Populate UoMs — exclude the base UoM row (shown separately as locked)
        $this->uoms = $item->uoms
            ->filter(fn ($u) => (string) $u->uom_id !== (string) $item->base_uom_id)
            ->map(fn ($u) => [
                'uom_id'              => (string) $u->uom_id,
                'factor_to_base'      => (string) $u->factor_to_base,
                'is_default_purchase' => (bool) $u->is_default_purchase,
                'is_default_issue'    => (bool) $u->is_default_issue,
            ])
            ->values()
            ->toArray();

        // Populate barcodes
        $this->barcodes = $item->barcodes
            ->map(fn ($b) => [
                'barcode'    => $b->barcode,
                'uom_id'     => (string) $b->uom_id,
                'type'       => $b->type instanceof \App\Enums\ItemBarcodeType ? $b->type->value : (string) $b->type,
                'is_primary' => (bool) $b->is_primary,
            ])
            ->values()
            ->toArray();
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return ItemCategory::active()->orderBy('name')->get();
    }

    #[Computed]
    public function uomOptions(): \Illuminate\Database\Eloquent\Collection
    {
        return UnitOfMeasure::active()->orderBy('code')->get();
    }

    #[Computed]
    public function virtualWarehouses(): \Illuminate\Database\Eloquent\Collection
    {
        return VirtualWarehouse::active()->orderBy('name')->get();
    }

    #[Computed]
    public function zones(): \Illuminate\Database\Eloquent\Collection
    {
        return \App\Models\Location::where('level', \App\Enums\LocationLevel::Zone->value)
            ->where('status', 'active')
            ->orderBy('code')
            ->get();
    }

    // ─── UoM rows ─────────────────────────────────────────────────────────────

    public function addUomRow(): void
    {
        $this->uoms[] = [
            'uom_id'              => '',
            'factor_to_base'      => '',
            'is_default_purchase' => false,
            'is_default_issue'    => false,
        ];
    }

    public function removeUomRow(int $index): void
    {
        unset($this->uoms[$index]);
        $this->uoms = array_values($this->uoms);
    }

    // ─── Barcode rows ─────────────────────────────────────────────────────────

    public function addBarcodeRow(): void
    {
        $this->barcodes[] = [
            'barcode'    => '',
            'uom_id'     => '',
            'type'       => 'internal',
            'is_primary' => false,
        ];
    }

    public function removeBarcodeRow(int $index): void
    {
        unset($this->barcodes[$index]);
        $this->barcodes = array_values($this->barcodes);
    }

    // ─── Tracking logic ───────────────────────────────────────────────────────

    public function updatedTrackExpiry(bool $value): void
    {
        if ($value) {
            $this->trackBatch = true;
        }
    }

    // ─── Save ─────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->validate($this->rules());

        UpdateItemAction::execute(
            item: $this->item,
            itemData: [
                'sku'                          => $this->sku,
                'name'                         => $this->name,
                'description'                  => $this->description ?: null,
                'category_id'                  => $this->categoryId ?: null,
                'base_uom_id'                  => $this->baseUomId,
                'default_virtual_warehouse_id' => $this->defaultVwId ?: null,
                'length'                       => $this->length ?: null,
                'width'                        => $this->width ?: null,
                'height'                       => $this->height ?: null,
                'weight'                       => $this->weight ?: null,
                'track_batch'                  => $this->trackBatch,
                'track_expiry'                 => $this->trackExpiry,
                'track_serial'                 => $this->trackSerial,
                'preferred_zone_id'            => $this->preferredZoneId ?: null,
                'min_stock'                    => $this->minStock ?: null,
                'max_stock'                    => $this->maxStock ?: null,
                'is_active'                    => $this->isActive,
                'allocation_rules'             => $this->allocationRules ?: null,
                'variance_threshold_pct'       => $this->varianceThresholdPct ?: null,
                'variance_threshold_qty'       => $this->varianceThresholdQty ?: null,
                'dispatch_requires_approval'   => $this->dispatchRequiresApproval,
                'dispatch_approval_qty'        => $this->dispatchApprovalQty ?: null,
                'adjustment_approval'          => $this->adjustmentApproval,
            ],
            uoms: $this->uoms,
            barcodes: $this->barcodes,
        );

        $this->dispatch('toast', message: 'Item updated successfully.', type: 'success');
        $this->redirect(route('admin.items.show', $this->item), navigate: true);
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    protected function rules(): array
    {
        return [
            'sku'                      => ['required', 'string', 'max:100', Rule::unique('items', 'sku')->ignore($this->item->id)],
            'name'                     => ['required', 'string', 'max:255'],
            'categoryId'               => ['nullable', 'exists:item_categories,id'],
            'baseUomId'                => ['required', 'exists:units_of_measure,id'],
            'defaultVwId'              => ['nullable', 'exists:virtual_warehouses,id'],
            'length'                   => ['nullable', 'numeric', 'min:0'],
            'width'                    => ['nullable', 'numeric', 'min:0'],
            'height'                   => ['nullable', 'numeric', 'min:0'],
            'weight'                   => ['nullable', 'numeric', 'min:0'],
            'preferredZoneId'          => ['nullable', 'exists:locations,id'],
            'minStock'                 => ['nullable', 'numeric', 'min:0'],
            'maxStock'                 => ['nullable', 'numeric', 'min:0'],
            'uoms.*.uom_id'            => ['nullable', 'exists:units_of_measure,id'],
            'uoms.*.factor_to_base'    => ['required_with:uoms.*.uom_id', 'nullable', 'numeric', 'min:0.0001'],
            'barcodes.*.barcode'         => ['required', 'string'],
            'barcodes.*.uom_id'          => ['required', 'exists:units_of_measure,id'],
            'allocationRules'            => ['nullable', 'array', new NoConflictingAllocationRules()],
            'allocationRules.*'          => ['string', 'in:fifo,fefo,lifo'],
            'varianceThresholdPct'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'varianceThresholdQty'       => ['nullable', 'numeric', 'min:0'],
            'dispatchRequiresApproval'   => ['boolean'],
            'dispatchApprovalQty'        => ['nullable', 'numeric', 'min:0'],
            'adjustmentApproval'         => ['required', 'in:inherit,required,not_required'],
        ];
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.items.edit')
            ->layout('components.layouts.app', [
                'title'           => 'Edit Item',
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
