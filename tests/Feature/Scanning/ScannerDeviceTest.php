<?php
declare(strict_types=1);
use App\Models\ScannerDevice;
use App\Services\Scanning\ScannerDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('auto-registers new device on first visit', function () {
    $service = new ScannerDeviceService();
    $request = \Illuminate\Http\Request::create('/scan');
    $device  = $service->resolveDevice($request, null);
    expect($device)->not->toBeNull()
        ->and(ScannerDevice::count())->toBe(1)
        ->and($device->is_active)->toBeTrue()
        ->and($device->name)->toStartWith('Device-');
});

it('returns null for deactivated device', function () {
    $uid = (string) \Illuminate\Support\Str::uuid();
    ScannerDevice::factory()->create(['device_uid'=>$uid,'is_active'=>false,'code'=>'SCN-T01']);
    $service = new ScannerDeviceService();
    $request = \Illuminate\Http\Request::create('/scan','GET',[],['wms_device_uid'=>$uid]);
    expect($service->resolveDevice($request, null))->toBeNull();
});

it('updates last_seen_at on resolve', function () {
    $uid    = (string) \Illuminate\Support\Str::uuid();
    $device = ScannerDevice::factory()->create(['device_uid'=>$uid,'is_active'=>true,'code'=>'SCN-T02','last_seen_at'=>null]);
    $service = new ScannerDeviceService();
    $request = \Illuminate\Http\Request::create('/scan','GET',[],['wms_device_uid'=>$uid]);
    $service->resolveDevice($request, null);
    expect($device->fresh()->last_seen_at)->not->toBeNull();
});

it('unreviewedCount counts recent auto-named devices', function () {
    ScannerDevice::factory()->create(['code'=>'SCN-A','name'=>'Device-abc12345','device_uid'=>(string)\Illuminate\Support\Str::uuid(),'created_at'=>now()]);
    ScannerDevice::factory()->create(['code'=>'SCN-B','name'=>'Custom Scanner','device_uid'=>(string)\Illuminate\Support\Str::uuid(),'created_at'=>now()]);
    ScannerDevice::factory()->create(['code'=>'SCN-C','name'=>'Device-old00001','device_uid'=>(string)\Illuminate\Support\Str::uuid(),'created_at'=>now()->subDays(10)]);
    expect((new ScannerDeviceService())->unreviewedCount())->toBe(1);
});

it('nextDeviceCode after SCN-099 returns SCN-100', function () {
    // Pre-seed SCN-001 through SCN-099 would be expensive; just seed the max
    for ($i = 97; $i <= 99; $i++) {
        ScannerDevice::factory()->create([
            'code'       => 'SCN-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'device_uid' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }
    $service = new ScannerDeviceService();
    $request = \Illuminate\Http\Request::create('/scan');
    $device  = $service->resolveDevice($request, null);
    expect($device->code)->toBe('SCN-100');
});
