<?php
declare(strict_types=1);
namespace App\Services\Scanning;

use App\Models\Printer;
use App\Models\ScannerDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ScannerDeviceService
{
    public function resolveDevice(Request $request, ?User $user = null): ?ScannerDevice
    {
        $uid = $request->cookie('wms_device_uid');

        if (! $uid) {
            $uid    = (string) Str::uuid();
            $device = $this->autoRegister($uid);
        } else {
            $device = ScannerDevice::where('device_uid', $uid)->first();
            if (! $device) {
                $device = $this->autoRegister($uid);
            }
        }

        // Amendment 11: deactivated device is blocked
        if (! $device->is_active) {
            return null;
        }

        $device->update([
            'last_user_id' => $user?->id,
            'last_seen_at' => now(),
        ]);

        // Store device code in session for the scan layout header
        session(['scan_device_code' => $device->code, 'scan_device_uid' => $uid]);

        return $device;
    }

    private function autoRegister(string $uid): ScannerDevice
    {
        // Amendment 11: new devices are immediately active
        $short = substr($uid, 0, 8);
        $code  = $this->nextDeviceCode();

        return ScannerDevice::create([
            'code'       => $code,
            'name'       => "Device-{$short}",
            'device_uid' => $uid,
            'is_active'  => true,
        ]);
    }

    private function nextDeviceCode(): string
    {
        $max = ScannerDevice::max('code');
        if (! $max) return 'SCN-001';
        // Extract numeric part: SCN-001 → 1
        $num = (int) ltrim(substr($max, 4), '0') ?: 0;
        return 'SCN-' . str_pad((string) ($num + 1), 3, '0', STR_PAD_LEFT);
    }

    public function unreviewedCount(): int
    {
        // Amendment 11: devices auto-named "Device-{uid}" created within 7 days = unreviewed
        return ScannerDevice::where('name', 'like', 'Device-%')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
    }
}
