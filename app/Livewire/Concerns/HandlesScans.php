<?php
declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\ScanResultType;
use App\Models\ScanEvent;
use App\Services\Scanning\BarcodeResolver;
use App\Services\Scanning\ScanContext;
use App\Services\Scanning\ScanResult;

/**
 * Shared barcode-scan handling for Livewire components.
 *
 * Usage:
 *   use HandlesScans;
 *
 * The component must have a public string $scanInput = '' property,
 * or override resolveScan() to read from a different field.
 *
 * Wire the view input: wire:model="scanInput" and call processScan
 * (or whatever method name the component exposes) which should call resolveScan().
 */
trait HandlesScans
{
    public string $scanInput = '';

    /**
     * Resolve the current $scanInput value via BarcodeResolver.
     * Clears $scanInput. Returns null for empty input.
     * Returns ScanResult for Ambiguous so the component can display a picker.
     *
     * @param  ScanResultType|null  $expects  Narrow ambiguous results to this type.
     * @param  int|null  $warehouseId          Passed into ScanContext.
     * @param  int|null  $virtualWarehouseId   Passed into ScanContext.
     */
    protected function resolveScan(
        ?ScanResultType $expects = null,
        ?int $warehouseId = null,
        ?int $virtualWarehouseId = null,
    ): ?ScanResult {
        $raw            = trim($this->scanInput);
        $this->scanInput = '';

        if ($raw === '') {
            return null;
        }

        $context = new ScanContext(
            expects:            $expects,
            warehouseId:        $warehouseId,
            virtualWarehouseId: $virtualWarehouseId,
        );

        $result = app(BarcodeResolver::class)->resolve($raw, $context);

        try {
            ScanEvent::create([
                'user_id'           => auth()->id(),
                'scanner_device_id' => session('scan_device_id'),
                'route_name'        => request()->route()?->getName(),
                'raw_value'         => $raw,
                'resolved_type'     => $result->ok() ? $result->type->value : null,
                'resolved_id'       => $result->record?->getKey(),
                'success'           => $result->ok(),
                'error_message'     => $result->ok() ? null : $result->error,
                'created_at'        => now(),
            ]);
        } catch (\Throwable) {
            // never block the scan
        }

        return $result;
    }

    /**
     * Dispatch the scan-success Livewire event (triggers beep + green flash).
     */
    protected function scanOk(string $message = 'Matched'): void
    {
        $this->dispatch('scan-success', message: $message);
    }

    /**
     * Dispatch the scan-error Livewire event (triggers double-beep + red flash).
     */
    protected function scanFail(string $message): void
    {
        $this->dispatch('scan-error', message: $message);
        $this->dispatch('toast', message: $message, type: 'error');
    }
}
