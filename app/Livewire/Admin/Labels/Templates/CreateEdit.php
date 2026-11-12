<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Labels\Templates;

use App\Enums\LabelTemplateType;
use App\Enums\LocationLevel;
use App\Models\LabelTemplate;
use App\Services\Labels\LabelLayoutBuilder;
use App\Services\Labels\LabelRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CreateEdit extends Component
{
    // ─── Model (null when creating) ───────────────────────────────────────────

    #[Locked]
    public ?LabelTemplate $template = null;

    // ─── Core fields ──────────────────────────────────────────────────────────

    public string $name       = '';
    public string $type       = 'pallet_lpn';
    public string $widthMm    = '100';
    public string $heightMm   = '150';
    public string $dpi        = '203';
    public bool   $isActive   = true;

    // ─── Simple-mode settings ─────────────────────────────────────────────────

    public string $layout       = 'barcode_top';
    public string $symbology    = 'code128';
    public array  $fields       = [];
    public bool   $showCompany  = false;
    public bool   $showCodeText = true;

    // ─── Location-level filter (Location type only) ───────────────────────────

    public array $forLevels = [];  // empty = all levels

    // ─── UI / mode ────────────────────────────────────────────────────────────

    public string $mode           = 'simple';  // 'simple' | 'custom'
    public bool   $showAdvanced   = false;
    public array  $elements       = [];
    public array  $previewWarnings = [];

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(?LabelTemplate $template = null): void
    {
        $this->authorize('labels.templates.manage');

        if ($template && $template->exists) {
            $this->template = $template;
            $this->hydrateFromModel($template);
        } else {
            $this->type      = 'pallet_lpn';
            $this->layout    = 'big_code';
            $this->symbology = 'code128';
            $this->fields    = $this->defaultFieldsFor(LabelTemplateType::PalletLpn);
            $this->rebuildElements();
        }
    }

    private function hydrateFromModel(LabelTemplate $tpl): void
    {
        $this->name       = $tpl->name;
        $this->type       = $tpl->type->value;
        $this->widthMm    = (string) $tpl->width_mm;
        $this->heightMm   = (string) $tpl->height_mm;
        $this->dpi        = (string) $tpl->dpi;
        $this->isActive   = $tpl->is_active;
        $this->elements   = $tpl->elements ?? [];
        $this->forLevels  = $tpl->for_levels ?? [];

        $s = $tpl->settings ?? [];
        if (! empty($s) && ($s['mode'] ?? 'custom') === 'simple') {
            $this->mode        = 'simple';
            $this->layout      = $s['layout']        ?? 'barcode_top';
            $this->symbology   = $s['symbology']     ?? 'code128';
            $this->fields      = $s['fields']        ?? [];
            $this->showCompany = $s['show_company']  ?? false;
            $this->showCodeText = $s['show_code_text'] ?? true;
        } else {
            $this->mode = 'custom';
        }

        $this->checkBounds();
    }

    // ─── Watchers that rebuild the preview ────────────────────────────────────

    public function updatedType(string $value): void
    {
        $enumType = LabelTemplateType::tryFrom($value);
        if ($enumType) {
            // Reset symbology for location (QR default), fields
            $this->symbology = $enumType === LabelTemplateType::Location ? 'qr' : 'code128';
            $this->fields    = $this->defaultFieldsFor($enumType);
        }
        $this->rebuildElements();
    }

    public function updatedLayout(): void     { $this->rebuildElements(); }
    public function updatedSymbology(): void  { $this->rebuildElements(); }
    public function updatedFields(): void     { $this->rebuildElements(); }
    public function updatedShowCompany(): void  { $this->rebuildElements(); }
    public function updatedShowCodeText(): void { $this->rebuildElements(); }
    public function updatedWidthMm(): void    { $this->rebuildElements(); }
    public function updatedHeightMm(): void   { $this->rebuildElements(); }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function availableFields(): array
    {
        $enumType = LabelTemplateType::tryFrom($this->type);
        if (! $enumType) {
            return [];
        }

        // Exclude company_name (handled by toggle) and main code (always present)
        $builder   = app(LabelLayoutBuilder::class);
        $mainField = $builder->mainCodeField($enumType);

        return array_filter(
            LabelTemplate::availableFields($enumType),
            fn($f) => $f !== 'company_name' && $f !== $mainField,
        );
    }

    #[Computed]
    public function locationLevels(): array
    {
        return LocationLevel::cases();
    }

    #[Computed]
    public function mainCodeField(): string
    {
        $enumType = LabelTemplateType::tryFrom($this->type);
        return $enumType ? app(LabelLayoutBuilder::class)->mainCodeField($enumType) : '';
    }

    #[Computed]
    public function svgPreview(): string
    {
        try {
            $tpl = new LabelTemplate([
                'width_mm'  => max(10, (float) ($this->widthMm  ?: 100)),
                'height_mm' => max(10, (float) ($this->heightMm ?: 50)),
                'dpi'       => (int) ($this->dpi ?: 203),
                'elements'  => $this->elements,
            ]);

            $enumType = LabelTemplateType::tryFrom($this->type);
            $sample   = $enumType ? $this->sampleRow($enumType) : [];

            return app(LabelRenderer::class)->toSvgPreview($tpl, $sample);
        } catch (\Throwable) {
            return '';
        }
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function save(bool $setDefault = false): void
    {
        $this->validate($this->rules());

        if ($this->mode === 'simple') {
            $this->rebuildElements();
        }

        $settings = $this->mode === 'simple' ? [
            'mode'           => 'simple',
            'layout'         => $this->layout,
            'symbology'      => $this->symbology,
            'fields'         => $this->fields,
            'show_company'   => $this->showCompany,
            'show_code_text' => $this->showCodeText,
        ] : null;

        $data = [
            'name'       => $this->name,
            'type'       => $this->type,
            'width_mm'   => (float) $this->widthMm,
            'height_mm'  => (float) $this->heightMm,
            'dpi'        => (int) $this->dpi,
            'elements'   => $this->elements,
            'settings'   => $settings,
            'for_levels' => $this->type === LabelTemplateType::Location->value && ! empty($this->forLevels)
                ? array_values($this->forLevels)
                : null,
            'is_active'  => $this->isActive,
        ];

        if ($this->template && $this->template->exists) {
            $this->template->update($data);
            $tpl = $this->template;
        } else {
            $data['is_default'] = false;
            $tpl = LabelTemplate::create($data);
            $this->template = $tpl;
        }

        if ($setDefault) {
            LabelTemplate::where('type', $tpl->type)
                ->where('id', '!=', $tpl->id)
                ->update(['is_default' => false]);
            $tpl->update(['is_default' => true]);
        }

        $this->dispatch('toast', message: 'Template saved.', type: 'success');
        unset($this->svgPreview);
    }

    public function saveAndSetDefault(): void
    {
        $this->save(setDefault: true);
    }

    public function resetToSimple(): void
    {
        $this->mode = 'simple';
        $this->rebuildElements();
    }

    public function testPrint(): void
    {
        if (! $this->template?->exists) {
            $this->dispatch('toast', message: 'Save the template first.', type: 'warning');
            return;
        }

        $enumType = LabelTemplateType::tryFrom($this->type);
        if ($enumType) {
            $this->dispatch('open-label-print', type: $enumType->value, ids: [$this->template->id], isTest: true);
        }
    }

    // Advanced element editor — mirrors Edit.php

    public function addElement(string $kind): void
    {
        $this->mode = 'custom';

        $defaults = match ($kind) {
            'text'    => ['kind' => 'text',    'x_mm' => 5, 'y_mm' => 5, 'w_mm' => 50, 'h_mm' => 8, 'value' => '', 'font_size_pt' => 10, 'bold' => false, 'align' => 'left'],
            'field'   => ['kind' => 'field',   'x_mm' => 5, 'y_mm' => 5, 'w_mm' => 50, 'h_mm' => 8, 'field_key' => '', 'font_size_pt' => 10, 'bold' => false, 'align' => 'left'],
            'barcode' => ['kind' => 'barcode', 'x_mm' => 5, 'y_mm' => 5, 'w_mm' => 40, 'h_mm' => 20, 'field_key' => '', 'symbology' => 'code128', 'show_text' => true],
            'line'    => ['kind' => 'line',    'x_mm' => 5, 'y_mm' => 5, 'w_mm' => 50, 'h_mm' => 1, 'thickness_mm' => 0.5],
            'box'     => ['kind' => 'box',     'x_mm' => 5, 'y_mm' => 5, 'w_mm' => 50, 'h_mm' => 20, 'thickness_mm' => 0.5],
            default   => ['kind' => $kind, 'x_mm' => 5, 'y_mm' => 5, 'w_mm' => 30, 'h_mm' => 10],
        };

        $this->elements[] = $defaults;
        $this->checkBounds();
        unset($this->svgPreview);
    }

    public function removeElement(int $idx): void
    {
        $this->mode = 'custom';
        unset($this->elements[$idx]);
        $this->elements = array_values($this->elements);
        $this->checkBounds();
        unset($this->svgPreview);
    }

    public function updateElement(int $idx, array $props): void
    {
        $this->mode = 'custom';
        if (isset($this->elements[$idx])) {
            $this->elements[$idx] = array_merge($this->elements[$idx], $props);
        }
        $this->checkBounds();
        unset($this->svgPreview);
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    private function rebuildElements(): void
    {
        if ($this->mode === 'custom') {
            return;
        }

        $enumType = LabelTemplateType::tryFrom($this->type);
        if (! $enumType) {
            return;
        }

        $w = max(10, (float) ($this->widthMm  ?: 100));
        $h = max(10, (float) ($this->heightMm ?: 50));

        $result = app(LabelLayoutBuilder::class)->build(
            $enumType,
            $w,
            $h,
            $this->layout,
            $this->symbology,
            $this->fields,
            $this->showCompany,
            $this->showCodeText,
        );

        $this->elements       = $result['elements'];
        $this->previewWarnings = $result['warnings'];
        $this->checkBounds();
        unset($this->svgPreview);
    }

    private function checkBounds(): void
    {
        $w = max(10, (float) ($this->widthMm  ?: 100));
        $h = max(10, (float) ($this->heightMm ?: 50));

        $this->previewWarnings = array_values(array_filter(
            $this->previewWarnings,
            fn ($warn) => ! str_starts_with($warn, 'Element '),
        ));

        foreach ($this->elements as $i => $el) {
            $x  = (float) ($el['x_mm'] ?? 0);
            $y  = (float) ($el['y_mm'] ?? 0);
            $ew = (float) ($el['w_mm'] ?? 0);
            $eh = (float) ($el['h_mm'] ?? 0);

            if ($x < 0 || $y < 0 || ($x + $ew) > $w || ($y + $eh) > $h) {
                $this->previewWarnings[] = 'Element ' . ($i + 1) . " ({$el['kind']}) extends outside the label area.";
            }
        }
    }

    private function defaultFieldsFor(LabelTemplateType $type): array
    {
        return match ($type) {
            LabelTemplateType::PalletLpn => ['item_name', 'qty', 'batch_no', 'expiry_date', 'vw_name', 'grn_number'],
            LabelTemplateType::Item      => ['item_name'],
            LabelTemplateType::Location  => ['warehouse_name'],
            LabelTemplateType::Container => ['iso_number', 'warehouse_name', 'status'],
            LabelTemplateType::Dispatch  => ['partner_name', 'qty'],
        };
    }

    private function sampleRow(LabelTemplateType $type): array
    {
        return match ($type) {
            LabelTemplateType::PalletLpn => ['lpn_code' => 'LPN-00001', 'grn_number' => 'GRN-2024-001', 'warehouse_name' => 'Main WH', 'item_sku' => 'SKU-001', 'item_name' => 'Sample Item', 'batch_no' => 'BATCH-01', 'expiry_date' => '2025-12-31', 'qty' => '100', 'uom_code' => 'PCS', 'vw_name' => 'VW-01', 'receive_date' => '2024-01-15', 'location_code' => 'A-01-01', 'company_name' => 'Acme Corp'],
            LabelTemplateType::Item      => ['item_sku' => 'SKU-001', 'item_name' => 'Sample Item', 'barcode' => '1234567890', 'batch_no' => 'BATCH-01', 'expiry_date' => '2025-12-31', 'qty' => '1', 'uom_code' => 'PCS', 'company_name' => 'Acme Corp'],
            LabelTemplateType::Location  => ['location_code' => 'A-01-01', 'location_level' => 'Bin', 'location_type' => 'storage', 'warehouse_name' => 'Main WH', 'barcode' => 'A-01-01', 'company_name' => 'Acme Corp'],
            LabelTemplateType::Container => ['container_no' => 'CONT-001', 'iso_number' => 'ABCD1234567', 'warehouse_name' => 'Main WH', 'status' => 'Available', 'company_name' => 'Acme Corp'],
            LabelTemplateType::Dispatch  => ['do_number' => 'DO-2024-001', 'partner_name' => 'Partner Ltd', 'warehouse_name' => 'Main WH', 'item_sku' => 'SKU-001', 'qty' => '50', 'company_name' => 'Acme Corp'],
        };
    }

    private function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'type'      => ['required', new Enum(LabelTemplateType::class)],
            'widthMm'   => ['required', 'numeric', 'min:10', 'max:500'],
            'heightMm'  => ['required', 'numeric', 'min:10', 'max:500'],
            'dpi'       => ['required', 'in:203,300'],
            'isActive'  => ['boolean'],
            'layout'    => ['required_if:mode,simple', 'in:big_code,barcode_left,barcode_top'],
            'symbology' => ['required_if:mode,simple', 'in:qr,code128,code39,ean13'],
        ];
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        $isEditing = $this->template && $this->template->exists;

        return view('livewire.admin.labels.templates.create-edit', [
            'isEditing'    => $isEditing,
            'templateTypes'=> LabelTemplateType::cases(),
        ])
            ->layout('components.layouts.app', [
                'title'           => $isEditing ? 'Edit Label Template' : 'New Label Template',
                'breadcrumbGroup' => 'Administration',
            ]);
    }
}
