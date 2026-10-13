<?php
declare(strict_types=1);
namespace Database\Seeders;

use App\Enums\LabelTemplateType;
use App\Enums\PrinterKind;
use App\Models\LabelTemplate;
use App\Models\Printer;
use App\Models\ScannerDevice;
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

    private function seedTemplates(): void
    {
        $templates = [
            [
                'name'      => 'Pallet / LPN Label',
                'type'      => LabelTemplateType::PalletLpn->value,
                'width_mm'  => 100,
                'height_mm' => 150,
                'dpi'       => 203,
                'is_default'=> true,
                'elements'  => [
                    ['id'=>'bc1','kind'=>'barcode','x_mm'=>5,'y_mm'=>5,'w_mm'=>90,'h_mm'=>35,'symbology'=>'code128','field_key'=>'lpn_code','show_text'=>true],
                    ['id'=>'t1','kind'=>'field','x_mm'=>5,'y_mm'=>45,'w_mm'=>90,'h_mm'=>8,'field_key'=>'lpn_code','font_size_pt'=>14,'bold'=>true,'align'=>'center'],
                    ['id'=>'t2','kind'=>'field','x_mm'=>5,'y_mm'=>57,'w_mm'=>90,'h_mm'=>7,'field_key'=>'grn_number','font_size_pt'=>9,'bold'=>false,'align'=>'left'],
                    ['id'=>'t3','kind'=>'field','x_mm'=>5,'y_mm'=>67,'w_mm'=>90,'h_mm'=>7,'field_key'=>'warehouse_name','font_size_pt'=>9,'bold'=>false,'align'=>'left'],
                    ['id'=>'t4','kind'=>'field','x_mm'=>5,'y_mm'=>77,'w_mm'=>90,'h_mm'=>7,'field_key'=>'item_name','font_size_pt'=>9,'bold'=>false,'align'=>'left'],
                    ['id'=>'t5','kind'=>'field','x_mm'=>5,'y_mm'=>87,'w_mm'=>44,'h_mm'=>7,'field_key'=>'batch_no','font_size_pt'=>9,'bold'=>false,'align'=>'left'],
                    ['id'=>'t6','kind'=>'field','x_mm'=>51,'y_mm'=>87,'w_mm'=>44,'h_mm'=>7,'field_key'=>'expiry_date','font_size_pt'=>9,'bold'=>false,'align'=>'right'],
                    ['id'=>'t7','kind'=>'field','x_mm'=>5,'y_mm'=>97,'w_mm'=>44,'h_mm'=>7,'field_key'=>'receive_date','font_size_pt'=>8,'bold'=>false,'align'=>'left'],
                    ['id'=>'ln1','kind'=>'line','x_mm'=>5,'y_mm'=>42,'w_mm'=>90,'h_mm'=>1,'thickness_mm'=>0.3],
                ],
            ],
            [
                'name'      => 'Item Label',
                'type'      => LabelTemplateType::Item->value,
                'width_mm'  => 50,
                'height_mm' => 25,
                'dpi'       => 203,
                'is_default'=> true,
                'elements'  => [
                    ['id'=>'bc1','kind'=>'barcode','x_mm'=>2,'y_mm'=>2,'w_mm'=>28,'h_mm'=>16,'symbology'=>'code128','field_key'=>'barcode','show_text'=>false],
                    ['id'=>'t1','kind'=>'field','x_mm'=>32,'y_mm'=>2,'w_mm'=>16,'h_mm'=>6,'field_key'=>'item_sku','font_size_pt'=>7,'bold'=>true,'align'=>'left'],
                    ['id'=>'t2','kind'=>'field','x_mm'=>32,'y_mm'=>10,'w_mm'=>16,'h_mm'=>6,'field_key'=>'item_name','font_size_pt'=>6,'bold'=>false,'align'=>'left'],
                ],
            ],
            [
                'name'      => 'Location / Bin Label',
                'type'      => LabelTemplateType::Location->value,
                'width_mm'  => 100,
                'height_mm' => 50,
                'dpi'       => 203,
                'is_default'=> true,
                'elements'  => [
                    ['id'=>'t1','kind'=>'field','x_mm'=>5,'y_mm'=>3,'w_mm'=>90,'h_mm'=>18,'field_key'=>'location_code','font_size_pt'=>28,'bold'=>true,'align'=>'center'],
                    ['id'=>'bc1','kind'=>'barcode','x_mm'=>5,'y_mm'=>24,'w_mm'=>55,'h_mm'=>20,'symbology'=>'qr','field_key'=>'barcode','show_text'=>false],
                    ['id'=>'t2','kind'=>'field','x_mm'=>63,'y_mm'=>28,'w_mm'=>34,'h_mm'=>7,'field_key'=>'warehouse_name','font_size_pt'=>8,'bold'=>false,'align'=>'left'],
                ],
            ],
            [
                'name'      => 'Container Label',
                'type'      => LabelTemplateType::Container->value,
                'width_mm'  => 100,
                'height_mm' => 150,
                'dpi'       => 203,
                'is_default'=> true,
                'elements'  => [
                    ['id'=>'bc1','kind'=>'barcode','x_mm'=>5,'y_mm'=>5,'w_mm'=>90,'h_mm'=>35,'symbology'=>'code128','field_key'=>'container_no','show_text'=>true],
                    ['id'=>'t1','kind'=>'field','x_mm'=>5,'y_mm'=>45,'w_mm'=>90,'h_mm'=>10,'field_key'=>'iso_number','font_size_pt'=>12,'bold'=>true,'align'=>'center'],
                    ['id'=>'t2','kind'=>'field','x_mm'=>5,'y_mm'=>60,'w_mm'=>90,'h_mm'=>8,'field_key'=>'warehouse_name','font_size_pt'=>10,'bold'=>false,'align'=>'left'],
                    ['id'=>'t3','kind'=>'field','x_mm'=>5,'y_mm'=>72,'w_mm'=>90,'h_mm'=>8,'field_key'=>'status','font_size_pt'=>10,'bold'=>false,'align'=>'left'],
                ],
            ],
            [
                'name'      => 'Dispatch Label',
                'type'      => LabelTemplateType::Dispatch->value,
                'width_mm'  => 100,
                'height_mm' => 150,
                'dpi'       => 203,
                'is_default'=> true,
                'elements'  => [
                    ['id'=>'bc1','kind'=>'barcode','x_mm'=>5,'y_mm'=>5,'w_mm'=>90,'h_mm'=>35,'symbology'=>'code128','field_key'=>'do_number','show_text'=>true],
                    ['id'=>'t1','kind'=>'field','x_mm'=>5,'y_mm'=>45,'w_mm'=>90,'h_mm'=>10,'field_key'=>'do_number','font_size_pt'=>14,'bold'=>true,'align'=>'center'],
                    ['id'=>'t2','kind'=>'field','x_mm'=>5,'y_mm'=>60,'w_mm'=>90,'h_mm'=>8,'field_key'=>'partner_name','font_size_pt'=>10,'bold'=>false,'align'=>'left'],
                ],
            ],
            [
                'name'      => 'A4 LPN Sheet (Fallback)',
                'type'      => LabelTemplateType::PalletLpn->value,
                'width_mm'  => 210,
                'height_mm' => 297,
                'dpi'       => 96,
                'is_default'=> false,
                'elements'  => [
                    ['id'=>'bc1','kind'=>'barcode','x_mm'=>10,'y_mm'=>10,'w_mm'=>100,'h_mm'=>50,'symbology'=>'code128','field_key'=>'lpn_code','show_text'=>true],
                    ['id'=>'t1','kind'=>'field','x_mm'=>10,'y_mm'=>65,'w_mm'=>190,'h_mm'=>12,'field_key'=>'lpn_code','font_size_pt'=>18,'bold'=>true,'align'=>'left'],
                    ['id'=>'t2','kind'=>'field','x_mm'=>10,'y_mm'=>82,'w_mm'=>190,'h_mm'=>9,'field_key'=>'grn_number','font_size_pt'=>11,'bold'=>false,'align'=>'left'],
                    ['id'=>'t3','kind'=>'field','x_mm'=>10,'y_mm'=>95,'w_mm'=>190,'h_mm'=>9,'field_key'=>'warehouse_name','font_size_pt'=>11,'bold'=>false,'align'=>'left'],
                ],
            ],
        ];

        foreach ($templates as $tpl) {
            LabelTemplate::firstOrCreate(
                ['name' => $tpl['name'], 'type' => $tpl['type']],
                $tpl,
            );
        }
    }

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
