<?php

namespace App\Livewire\Admin\Items;

use App\Actions\Foundation\CreateItemAction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UnitOfMeasure;
use App\Models\VirtualWarehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Create extends Component
{
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

    // Stock levels tab
    public string $minStock = '';
    public string $maxStock = '';

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

        CreateItemAction::execute(
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
                'min_stock'                    => $this->minStock ?: null,
                'max_stock'                    => $this->maxStock ?: null,
                'is_active'                    => $this->isActive,
            ],
            uoms: $this->uoms,
            barcodes: $this->barcodes,
            baseUomId: (int) $this->baseUomId,
        );

        $this->dispatch('toast', message: 'Item created successfully.', type: 'success');
        $this->redirect(route('admin.items.index'), navigate: true);
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    protected function rules(): array
    {
        return [
            'sku'                      => ['required', 'string', 'max:100', Rule::unique('items', 'sku')],
            'name'                     => ['required', 'string', 'max:255'],
            'categoryId'               => ['nullable', 'exists:item_categories,id'],
            'baseUomId'                => ['required', 'exists:units_of_measure,id'],
            'defaultVwId'              => ['nullable', 'exists:virtual_warehouses,id'],
            'length'                   => ['nullable', 'numeric', 'min:0'],
            'width'                    => ['nullable', 'numeric', 'min:0'],
            'height'                   => ['nullable', 'numeric', 'min:0'],
            'weight'                   => ['nullable', 'numeric', 'min:0'],
            'minStock'                 => ['nullable', 'numeric', 'min:0'],
            'maxStock'                 => ['nullable', 'numeric', 'min:0'],
            'uoms.*.uom_id'            => ['nullable', 'exists:units_of_measure,id'],
            'uoms.*.factor_to_base'    => ['required_with:uoms.*.uom_id', 'nullable', 'numeric', 'min:0.0001'],
            'barcodes.*.barcode'       => ['required', 'string', Rule::unique('item_barcodes', 'barcode')],
            'barcodes.*.uom_id'        => ['required', 'exists:units_of_measure,id'],
        ];
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.items.create')
            ->layout('components.layouts.app', [
                'title'           => 'New Item',
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
