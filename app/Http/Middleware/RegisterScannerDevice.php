<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Scanning\ScannerDeviceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auto-register the current browser as a scanner device on the first
 * authenticated request of a session.  Runs silently — deactivated devices
 * are not blocked here (only the mobile scan home enforces that).
 */
class RegisterScannerDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! session()->has('scan_device_uid')) {
            try {
                app(ScannerDeviceService::class)->resolveDevice($request, $request->user());
            } catch (\Throwable) {
                // Never block a request because device registration failed
            }
        }

        return $next($request);
    }
}
