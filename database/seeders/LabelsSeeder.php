<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LabelTemplateType;
use App\Enums\PrinterKind;
use App\Models\LabelTemplate;
use App\Models\Printer;
use App\Models\ScannerDevice;
use App\Services\Labels\LabelLayoutBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LabelsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTemplates();
        $this->seedPrinters();
        $this->seedScannerDevices();
    }

    // ─── Templates ────────────────────────────────────────────────────────────

    private function seedTemplates(): void
    {
        $builder = app(LabelLayoutBuilder::class);

        // Each entry with layout !== null is a "simple mode" template.
        // is_default = true means this is the default for its type;
        // exactly one per type should be true.
        $definitions = [

            // ── Pallet / LPN ──────────────────────────────────────────────
            [
                'name'           => 'Pallet LPN - Standard',
                'type'           => LabelTemplateType::PalletLpn,
                'width_mm'       => 100,
                'height_mm'      => 150,
                'dpi'            => 203,
                'is_default'     => true,
                'layout'         => 'barcode_top',
                'symbology'      => 'code128',
                'fields'         => ['item_name', 'qty', 'uom_code', 'batch_no', 'expiry_date', 'grn_number'],
                'show_company'   => false,
                'show_code_text' => true,
            ],
            [
                'name'           => 'Pallet LPN - Compact',
                'type'           => LabelTemplateType::PalletLpn,
                'width_mm'       => 100,
                'height_mm'      => 100,
                'dpi'            => 203,
                'is_default'     => false,
                'layout'         => 'barcode_left',
                'symbology'      => 'code128',
                'fields'         => ['item_name', 'qty', 'batch_no', 'expiry_date'],
                'show_company'   => false,
                'show_code_text' => true,
            ],
            [
                'name'           => 'Pallet LPN - Big Code',
                'type'           => LabelTemplateType::PalletLpn,
                'width_mm'       => 100,
                'height_mm'      => 150,
                'dpi'            => 203,
                'is_default'     => false,
                'layout'         => 'big_code',
                'symbology'      => 'code128',
                'fields'         => ['item_name', 'qty', 'batch_no', 'expiry_date', 'vw_name'],
                'show_company'   => false,
                'show_code_text' => true,
            ],
            [
                'name'           => 'Pallet LPN - A4 Sheet',
                'type'           => LabelTemplateType::PalletLpn,
                'width_mm'       => 210,
                'height_mm'      => 297,
                'dpi'            => 203,
                'is_default'     => false,
                'layout'         => null, // custom elements below
            ],

            // ── Location / Bin ────────────────────────────────────────────
            [
                'name'           => 'Location - Standard',
                'type'           => LabelTemplateType::Location,
                'width_mm'       => 100,
                'height_mm'      => 50,
                'dpi'            => 203,
                'is_default'     => true,
                'layout'         => 'barcode_left',
                'symbology'      => 'qr',
                'fields'         => ['location_level', 'warehouse_name'],
                'show_company'   => false,
                'show_code_text' => false,
                'for_levels'     => null,  // all levels
            ],
            [
                'name'           => 'Location - Barcode',
                'type'           => LabelTemplateType::Location,
                'width_mm'       => 100,
                'height_mm'      => 50,
                'dpi'            => 203,
                'is_default'     => false,
                'layout'         => 'barcode_top',
                'symbology'      => 'code128',
                'fields'         => ['location_level', 'warehouse_name'],
                'show_company'   => false,
                'show_code_text' => true,
                'for_levels'     => null,  // all levels
            ],
            [
                'name'           => 'Location - Mini',
                'type'           => LabelTemplateType::Location,
                'width_mm'       => 50,
                'height_mm'      => 25,
                'dpi'            => 203,
                'is_default'     => false,
                'layout'         => 'barcode_left',
                'symbology'      => 'qr',
                'fields'         => ['location_level'],
                'show_company'   => false,
                'show_code_text' => false,
                'for_levels'     => ['bin', 'shelf_level'],  // compact; best for shelves and bins
            ],

            // ── Item ──────────────────────────────────────────────────────
            [
                'name'           => 'Item - Standard',
                'type'           => LabelTemplateType::Item,
                'width_mm'       => 50,
                'height_mm'      => 25,
                'dpi'            => 203,
                'is_default'     => true,
                'layout'         => 'barcode_left',
                'symbology'      => 'code128',
                'fields'         => ['item_name'],
                'show_company'   => false,
                'show_code_text' => false,
            ],
            [
                'name'           => 'Item - Full',
                'type'           => LabelTemplateType::Item,
                'width_mm'       => 100,
                'height_mm'      => 50,
                'dpi'            => 203,
                'is_default'     => false,
                'layout'         => 'barcode_top',
                'symbology'      => 'code128',
                'fields'         => ['item_name', 'batch_no', 'expiry_date', 'qty', 'uom_code'],
                'show_company'   => false,
                'show_code_text' => true,
            ],
            [
                'name'           => 'Item - QR',
                'type'           => LabelTemplateType::Item,
                'width_mm'       => 50,
                'height_mm'      => 50,
                'dpi'            => 203,
                'is_default'     => false,
                'layout'         => 'big_code',
                'symbology'      => 'qr',
                'fields'         => ['item_name'],
                'show_company'   => false,
                'show_code_text' => false,
            ],

            // ── Container ─────────────────────────────────────────────────
            [
                'name'           => 'Container - Standard',
                'type'           => LabelTemplateType::Container,
                'width_mm'       => 100,
                'height_mm'      => 150,
                'dpi'            => 203,
                'is_default'     => true,
                'layout'         => 'barcode_top',
                'symbology'      => 'code128',
                'fields'         => ['iso_number', 'warehouse_name', 'status'],
                'show_company'   => false,
                'show_code_text' => true,
            ],
            [
                'name'           => 'Container - Compact',
                'type'           => LabelTemplateType::Container,
                'width_mm'       => 100,
                'height_mm'      => 100,
                'dpi'            => 203,
                'is_default'     => false,
                'layout'         => 'barcode_left',
                'symbology'      => 'code128',
                'fields'         => ['iso_number', 'status'],
                'show_company'   => false,
                'show_code_text' => true,
            ],
            [
                'name'           => 'Container - Big Code',
                'type'           => LabelTemplateType::Container,
                'width_mm'       => 100,
                'height_mm'      => 150,
                'dpi'            => 203,
                'is_default'     => false,
                'layout'         => 'big_code',
                'symbology'      => 'code128',
                'fields'         => ['iso_number', 'warehouse_name', 'status'],
                'show_company'   => false,
                'show_code_text' => true,
            ],

            // ── Dispatch / Delivery Order ──────────────────────────────────
            [
                'name'           => 'Dispatch - Standard',
                'type'           => LabelTemplateType::Dispatch,
                'width_mm'       => 100,
                'height_mm'      => 150,
                'dpi'            => 203,
                'is_default'     => true,
                'layout'         => 'barcode_top',
                'symbology'      => 'code128',
                'fields'         => ['partner_name', 'warehouse_name', 'qty'],
                'show_company'   => false,
                'show_code_text' => true,
            ],
            [
                'name'           => 'Dispatch - Compact',
                'type'           => LabelTemplateType::Dispatch,
                'width_mm'       => 100,
                'height_mm'      => 100,
                'dpi'            => 203,
                'is_default'     => false,
                'layout'         => 'barcode_left',
                'symbology'      => 'code128',
                'fields'         => ['partner_name', 'qty'],
                'show_company'   => false,
                'show_code_text' => true,
            ],
            [
                'name'           => 'Dispatch - Big Code',
                'type'           => LabelTemplateType::Dispatch,
                'width_mm'       => 100,
                'height_mm'      => 150,
                'dpi'            => 203,
                'is_default'     => false,
                'layout'         => 'big_code',
                'symbology'      => 'code128',
                'fields'         => ['partner_name', 'warehouse_name', 'item_sku', 'qty'],
                'show_company'   => false,
                'show_code_text' => true,
            ],
        ];

        foreach ($definitions as $def) {
            $type = $def['type'];

            if ($def['layout'] !== null) {
                $result = $builder->build(
                    $type,
                    (float) $def['width_mm'],
                    (float) $def['height_mm'],
                    $def['layout'],
                    $def['symbology'],
                    $def['fields'],
                    $def['show_company'],
                    $def['show_code_text'],
                );

                $settings = [
                    'mode'           => 'simple',
                    'layout'         => $def['layout'],
                    'symbology'      => $def['symbology'],
                    'fields'         => $def['fields'],
                    'show_company'   => $def['show_company'],
                    'show_code_text' => $def['show_code_text'],
                ];

                LabelTemplate::updateOrCreate(
                    ['name' => $def['name'], 'type' => $type->value],
                    [
                        'width_mm'   => $def['width_mm'],
                        'height_mm'  => $def['height_mm'],
                        'dpi'        => $def['dpi'],
                        'is_default' => $def['is_default'],
                        'is_active'  => true,
                        'elements'   => $result['elements'],
                        'settings'   => $settings,
                        'for_levels' => $def['for_levels'] ?? null,
                    ],
                );
            } else {
                // A4 fallback — custom elements, no simple-mode settings
                LabelTemplate::firstOrCreate(
                    ['name' => $def['name'], 'type' => $type->value],
                    [
                        'width_mm'   => $def['width_mm'],
                        'height_mm'  => $def['height_mm'],
                        'dpi'        => $def['dpi'],
                        'is_default' => $def['is_default'],
                        'is_active'  => true,
                        'elements'   => [
                            ['id' => 'bc1', 'kind' => 'barcode', 'x_mm' => 10, 'y_mm' => 10, 'w_mm' => 190, 'h_mm' => 60, 'symbology' => 'code128', 'field_key' => 'lpn_code', 'show_text' => true],
                            ['id' => 't1',  'kind' => 'field',   'x_mm' => 10, 'y_mm' => 76, 'w_mm' => 190, 'h_mm' => 14, 'field_key' => 'lpn_code',       'font_size_pt' => 18, 'bold' => true,  'align' => 'left'],
                            ['id' => 't2',  'kind' => 'field',   'x_mm' => 10, 'y_mm' => 95, 'w_mm' => 190, 'h_mm' => 10, 'field_key' => 'item_name',      'font_size_pt' => 12, 'bold' => false, 'align' => 'left'],
                            ['id' => 't3',  'kind' => 'field',   'x_mm' => 10, 'y_mm' => 108,'w_mm' => 90,  'h_mm' => 10, 'field_key' => 'grn_number',     'font_size_pt' => 12, 'bold' => false, 'align' => 'left'],
                            ['id' => 't4',  'kind' => 'field',   'x_mm' => 110,'y_mm' => 108,'w_mm' => 90,  'h_mm' => 10, 'field_key' => 'warehouse_name', 'font_size_pt' => 12, 'bold' => false, 'align' => 'right'],
                        ],
                        'settings' => null,
                    ],
                );
            }
        }

        // Enforce is_default uniqueness per type: for each type, only the
        // definition marked is_default = true should remain the default.
        $defaultNames = collect($definitions)
            ->where('is_default', true)
            ->mapWithKeys(fn ($d) => [$d['type']->value => $d['name']]);

        foreach ($defaultNames as $typeValue => $defaultName) {
            LabelTemplate::where('type', $typeValue)
                ->where('name', '!=', $defaultName)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }

    // ─── Printers ─────────────────────────────────────────────────────────────

    private function seedPrinters(): void
    {
        Printer::firstOrCreate(
            ['name' => 'Default PDF Printer'],
            ['kind' => PrinterKind::PdfBrowser->value, 'dpi' => 203, 'is_active' => true],
        );
        Printer::firstOrCreate(
            ['name' => 'ZPL Download'],
            ['kind' => PrinterKind::ZplDownload->value, 'dpi' => 203, 'is_active' => true],
        );
    }

    // ─── Scanner devices ──────────────────────────────────────────────────────

    private function seedScannerDevices(): void
    {
        ScannerDevice::firstOrCreate(
            ['code' => 'SCN-001'],
            ['name' => 'Warehouse Scanner 1', 'device_uid' => (string) Str::uuid(), 'is_active' => true],
        );
        ScannerDevice::firstOrCreate(
            ['code' => 'SCN-002'],
            ['name' => 'Warehouse Scanner 2', 'device_uid' => (string) Str::uuid(), 'is_active' => true],
        );
    }
}
