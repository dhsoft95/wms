<?php
declare(strict_types=1);
use App\Enums\ScanResultType;
use App\Services\Scanning\BarcodeResolver;
use App\Services\Scanning\ScanContext;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->resolver = new BarcodeResolver();
});

it('returns unknown for empty string', function () {
    expect($this->resolver->resolve('')->type)->toBe(ScanResultType::Unknown);
});

it('returns unknown for unrecognised barcode', function () {
    expect($this->resolver->resolve('XYZNOTHING999')->type)->toBe(ScanResultType::Unknown);
});

it('resolves LPN by LPN- prefix', function () {
    $wh  = \App\Models\Warehouse::factory()->create();
    $lpn = \App\Models\Inventory\Lpn::factory()->create(['code' => 'LPN-TST001', 'warehouse_id' => $wh->id]);
    $r   = $this->resolver->resolve('LPN-TST001');
    expect($r->type)->toBe(ScanResultType::Lpn)->and($r->record->id)->toBe($lpn->id);
});

it('returns unknown for LPN- prefix that does not exist', function () {
    expect($this->resolver->resolve('LPN-DOESNOTEXIST')->type)->toBe(ScanResultType::Unknown);
});

it('resolves GRN by GRN- prefix', function () {
    $wh  = \App\Models\Warehouse::factory()->create();
    $grn = \App\Models\Grn::factory()->create(['number' => 'GRN-TTTTT', 'warehouse_id' => $wh->id]);
    expect($this->resolver->resolve('GRN-TTTTT')->type)->toBe(ScanResultType::Grn);
});

it('resolves container by CTR- prefix', function () {
    $wh = \App\Models\Warehouse::factory()->create();
    \App\Models\Container::factory()->create(['internal_number' => 'CTR-00555', 'warehouse_id' => $wh->id]);
    expect($this->resolver->resolve('CTR-00555')->type)->toBe(ScanResultType::Container);
});

it('resolves valid ISO container number (CSQU3054188)', function () {
    $wh = \App\Models\Warehouse::factory()->create();
    \App\Models\Container::factory()->create(['iso_number' => 'CSQU3054188', 'warehouse_id' => $wh->id]);
    expect($this->resolver->resolve('CSQU3054188')->type)->toBe(ScanResultType::Container);
});

it('does not treat invalid ISO as container', function () {
    expect($this->resolver->resolve('ABCD1234566')->type)->not->toBe(ScanResultType::Container);
});

it('resolves item by item_barcodes.barcode', function () {
    $item = \App\Models\Item::factory()->create();
    \App\Models\ItemBarcode::factory()->create(['item_id' => $item->id, 'barcode' => 'EAN-TESTBC001']);
    expect($this->resolver->resolve('EAN-TESTBC001')->type)->toBe(ScanResultType::Item);
});

it('resolves item by SKU', function () {
    \App\Models\Item::factory()->create(['sku' => 'SKU-LOOKUPTEST']);
    expect($this->resolver->resolve('SKU-LOOKUPTEST')->type)->toBe(ScanResultType::Item);
});

it('resolves location by code', function () {
    $wh = \App\Models\Warehouse::factory()->create();
    \App\Models\Location::factory()->create(['code' => 'Z-99-99', 'warehouse_id' => $wh->id]);
    expect($this->resolver->resolve('Z-99-99')->type)->toBe(ScanResultType::Location);
});

it('resolves serial number', function () {
    $item = \App\Models\Item::factory()->create();
    \App\Models\Inventory\SerialNumber::factory()->create(['serial' => 'SN-TESTSERIAL', 'item_id' => $item->id]);
    expect($this->resolver->resolve('SN-TESTSERIAL')->type)->toBe(ScanResultType::Serial);
});

it('detects GS1 with ]C1 prefix and parses batch and expiry', function () {
    $item = \App\Models\Item::factory()->create();
    \App\Models\ItemBarcode::factory()->create(['item_id' => $item->id, 'barcode' => '0614141123452']);
    $raw = ']C1' . '0100614141123452' . '10LOT001' . chr(29) . '17261231';
    $r   = $this->resolver->resolve($raw);
    expect($r->type)->toBe(ScanResultType::Item)
        ->and($r->gs1Data['batch'])->toBe('LOT001')
        ->and($r->gs1Data['expiry'])->toBe('2026-12-31');
});

it('parses GS1 human-readable (01) format', function () {
    $item = \App\Models\Item::factory()->create();
    \App\Models\ItemBarcode::factory()->create(['item_id' => $item->id, 'barcode' => '0614141123452']);
    $raw = '(01)00614141123452(10)BATCH99(17)261015';
    $r   = $this->resolver->resolve($raw);
    expect($r->type)->toBe(ScanResultType::Item)
        ->and($r->gs1Data['expiry'])->toBe('2026-10-15');
});

it('normalizes GTIN-14 to match EAN-13 in item_barcodes', function () {
    $item = \App\Models\Item::factory()->create();
    \App\Models\ItemBarcode::factory()->create(['item_id' => $item->id, 'barcode' => '5901234123457']);
    // GTIN-14 with indicator 0: 05901234123457
    $raw = ']C1' . '0105901234123457';
    expect($this->resolver->resolve($raw)->type)->toBe(ScanResultType::Item);
});

it('parses qty AI (30) from GS1', function () {
    $item = \App\Models\Item::factory()->create();
    \App\Models\ItemBarcode::factory()->create(['item_id' => $item->id, 'barcode' => '0614141123452']);
    $r = $this->resolver->resolve('(01)00614141123452(30)25');
    expect($r->gs1Data['qty'])->toBe(25);
});

it('parses serial AI (21) from GS1', function () {
    $item = \App\Models\Item::factory()->create();
    \App\Models\ItemBarcode::factory()->create(['item_id' => $item->id, 'barcode' => '0614141123452']);
    $raw = ']C1' . '0100614141123452' . chr(29) . '21SN12345';
    expect($this->resolver->resolve($raw)->gs1Data['serial'])->toBe('SN12345');
});

it('returns unknown for GS1 with unrecognised GTIN', function () {
    expect($this->resolver->resolve('(01)09999999999994')->type)->toBe(ScanResultType::Unknown);
});

// ─── New prefix tests ─────────────────────────────────────────────────────────

it('resolves DO- prefix to DispatchOrder', function () {
    $wh = \App\Models\Warehouse::factory()->create();
    $vw = \App\Models\VirtualWarehouse::factory()->create(['warehouse_id' => $wh->id]);
    $do = \App\Models\DeliveryOrder::factory()->create(['number' => 'DO-2025-000099']);
    $r  = $this->resolver->resolve('DO-2025-000099');
    expect($r->type)->toBe(ScanResultType::DispatchOrder)->and($r->record->id)->toBe($do->id);
});

it('returns unknown for DO- that does not exist', function () {
    expect($this->resolver->resolve('DO-9999-999999')->type)->toBe(ScanResultType::Unknown);
});

it('resolves PCK- prefix to PickList', function () {
    $do  = \App\Models\DeliveryOrder::factory()->create();
    $pl  = \App\Models\PickList::create(['number' => 'PCK-2025-000001', 'delivery_order_id' => $do->id, 'status' => 'open']);
    $r   = $this->resolver->resolve('PCK-2025-000001');
    expect($r->type)->toBe(ScanResultType::PickList)->and($r->record->id)->toBe($pl->id);
});

it('returns unknown for PCK- that does not exist', function () {
    expect($this->resolver->resolve('PCK-9999-999999')->type)->toBe(ScanResultType::Unknown);
});

it('resolves DSP- prefix to Dispatch', function () {
    $do  = \App\Models\DeliveryOrder::factory()->create();
    $dsp = \App\Models\Dispatch::create([
        'number'           => 'DSP-2025-000001',
        'delivery_order_id' => $do->id,
        'vehicle_reg_no'   => 'T-123-ABC',
        'driver_name'      => 'Test Driver',
    ]);
    $r = $this->resolver->resolve('DSP-2025-000001');
    expect($r->type)->toBe(ScanResultType::Dispatch)->and($r->record->id)->toBe($dsp->id);
});

it('returns unknown for DSP- that does not exist', function () {
    expect($this->resolver->resolve('DSP-9999-999999')->type)->toBe(ScanResultType::Unknown);
});

it('resolves GP- prefix to GatePass', function () {
    $do  = \App\Models\DeliveryOrder::factory()->create();
    $dsp = \App\Models\Dispatch::create([
        'number'           => 'DSP-2025-000002',
        'delivery_order_id' => $do->id,
        'vehicle_reg_no'   => 'T-456-DEF',
        'driver_name'      => 'Test Driver',
    ]);
    $gp = \App\Models\GatePass::create(['number' => 'GP-2025-000001', 'dispatch_id' => $dsp->id, 'status' => 'issued']);
    $r  = $this->resolver->resolve('GP-2025-000001');
    expect($r->type)->toBe(ScanResultType::GatePass)->and($r->record->id)->toBe($gp->id);
});

it('returns unknown for GP- that does not exist', function () {
    expect($this->resolver->resolve('GP-9999-999999')->type)->toBe(ScanResultType::Unknown);
});

// ─── AIM identifier stripping ─────────────────────────────────────────────────

it('strips AIM ]E0 prefix before resolving LPN', function () {
    $wh  = \App\Models\Warehouse::factory()->create();
    $lpn = \App\Models\Inventory\Lpn::factory()->create(['code' => 'LPN-AIM001', 'warehouse_id' => $wh->id]);
    expect($this->resolver->resolve(']E0LPN-AIM001')->type)->toBe(ScanResultType::Lpn);
});

it('strips AIM ]C0 prefix before resolving item SKU', function () {
    \App\Models\Item::factory()->create(['sku' => 'SKU-AIMTEST']);
    expect($this->resolver->resolve(']C0SKU-AIMTEST')->type)->toBe(ScanResultType::Item);
});

it('strips AIM ]Q3 prefix before resolving location', function () {
    $wh = \App\Models\Warehouse::factory()->create();
    \App\Models\Location::factory()->create(['code' => 'AIM-BIN-01', 'warehouse_id' => $wh->id]);
    expect($this->resolver->resolve(']Q3AIM-BIN-01')->type)->toBe(ScanResultType::Location);
});

// ─── GS1 unknown AI skips rather than aborts ──────────────────────────────────

it('skips unknown GS1 AI and still extracts GTIN and batch', function () {
    $item = \App\Models\Item::factory()->create();
    \App\Models\ItemBarcode::factory()->create(['item_id' => $item->id, 'barcode' => '0614141123452']);
    // AI 99 is not in our parser — should skip it and continue to AI 10
    $gs = chr(29);
    $raw = '0100614141123452' . $gs . '99UNKNOWN' . $gs . '10BATCH77';
    $r   = $this->resolver->resolve($raw);
    expect($r->type)->toBe(ScanResultType::Item)
        ->and($r->gs1Data['batch'])->toBe('BATCH77');
});

// ─── Case-insensitive location lookup ────────────────────────────────────────

it('resolves location code case-insensitively', function () {
    $wh = \App\Models\Warehouse::factory()->create();
    \App\Models\Location::factory()->create(['code' => 'A-01-02', 'warehouse_id' => $wh->id]);
    expect($this->resolver->resolve('a-01-02')->type)->toBe(ScanResultType::Location);
    expect($this->resolver->resolve('A-01-02')->type)->toBe(ScanResultType::Location);
});
