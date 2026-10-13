<?php
declare(strict_types=1);
use App\Models\LabelTemplate;
use App\Services\Labels\LabelRenderer;

beforeEach(fn() => $this->renderer = new LabelRenderer());

function tpl(float $w, float $h, int $dpi = 203, array $els = []): LabelTemplate {
    $t = new LabelTemplate();
    $t->width_mm = $w; $t->height_mm = $h; $t->dpi = $dpi; $t->elements = $els;
    return $t;
}

it('PDF output starts with %PDF', function () {
    $pdf = $this->renderer->toPdf(tpl(100, 150), [[]]);
    expect($pdf)->toStartWith('%PDF');
});

it('ZPL contains ^XA and ^XZ', function () {
    $zpl = $this->renderer->toZpl(tpl(100, 50), [[]]);
    expect($zpl)->toContain('^XA')->toContain('^XZ');
});

it('mm to dots at 203 dpi: 25.4mm = 203 dots', function () {
    $zpl = $this->renderer->toZpl(tpl(100, 50, 203, [['kind'=>'line','x_mm'=>0,'y_mm'=>25.4,'w_mm'=>10,'h_mm'=>1,'thickness_mm'=>0.5]]), [[]]);
    expect($zpl)->toContain('^FO0,203');
});

it('mm to dots at 300 dpi: 25.4mm = 300 dots', function () {
    $zpl = $this->renderer->toZpl(tpl(100, 50, 300, [['kind'=>'line','x_mm'=>0,'y_mm'=>25.4,'w_mm'=>10,'h_mm'=>1,'thickness_mm'=>0.5]]), [[]]);
    expect($zpl)->toContain('^FO0,300');
});

it('Code128 renders ^BC in ZPL', function () {
    $zpl = $this->renderer->toZpl(tpl(100, 50, 203, [['kind'=>'barcode','x_mm'=>5,'y_mm'=>5,'w_mm'=>80,'h_mm'=>30,'symbology'=>'code128','field_key'=>'v','show_text'=>true]]), [['v'=>'TEST123']]);
    expect($zpl)->toContain('^BC');
});

it('QR renders ^BQ in ZPL', function () {
    $zpl = $this->renderer->toZpl(tpl(100, 50, 203, [['kind'=>'barcode','x_mm'=>5,'y_mm'=>5,'w_mm'=>30,'h_mm'=>30,'symbology'=>'qr','field_key'=>'v','show_text'=>false]]), [['v'=>'TEST']]);
    expect($zpl)->toContain('^BQ');
});

it('EAN-13 renders ^BE in ZPL', function () {
    $zpl = $this->renderer->toZpl(tpl(100, 50, 203, [['kind'=>'barcode','x_mm'=>5,'y_mm'=>5,'w_mm'=>70,'h_mm'=>25,'symbology'=>'ean13','field_key'=>'v','show_text'=>true]]), [['v'=>'5901234123457']]);
    expect($zpl)->toContain('^BE');
});

it('DataMatrix renders ^BX in ZPL', function () {
    $zpl = $this->renderer->toZpl(tpl(100, 50, 203, [['kind'=>'barcode','x_mm'=>5,'y_mm'=>5,'w_mm'=>25,'h_mm'=>25,'symbology'=>'datamatrix','field_key'=>'v','show_text'=>false]]), [['v'=>'TEST']]);
    expect($zpl)->toContain('^BX');
});

it('renders N copies as N ^XA blocks', function () {
    $zpl = $this->renderer->toZpl(tpl(50, 25), [['k'=>'v']], 3);
    expect(substr_count($zpl, '^XA'))->toBe(3);
});

it('text element value appears in ZPL', function () {
    $zpl = $this->renderer->toZpl(tpl(50, 25, 203, [['kind'=>'text','x_mm'=>0,'y_mm'=>0,'w_mm'=>50,'h_mm'=>10,'value'=>'HELLO','font_size_pt'=>10,'bold'=>false,'align'=>'left']]), [[]]);
    expect($zpl)->toContain('^FDHELLO^FS');
});
