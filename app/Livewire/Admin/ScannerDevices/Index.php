<?php

declare(strict_types=1);

namespace App\Livewire\Admin\ScannerDevices;

use App\Models\Printer;
use App\Models\ScannerDevice;
use App\Services\Scanning\ScannerDeviceService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->authorize('scanner-devices.manage');
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function devices(): LengthAwarePaginator
    {
        return ScannerDevice::with(['defaultPrinter', 'lastUser'])
            ->orderBy('code')
            ->paginate(20);
    }

    #[Computed]
    public function unreviewedCount(): int
    {
        return app(ScannerDeviceService::class)->unreviewedCount();
    }

    #[Computed]
    public function activePrinters(): \Illuminate\Database\Eloquent\Collection
    {
        return Printer::active()->orderBy('name')->get();
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function updateName(int $id, string $name): void
    {
        $device = ScannerDevice::findOrFail($id);
        $device->update(['name' => $name]);

        $this->dispatch('toast', message: "Device name updated.", type: 'success');
        unset($this->devices);
    }

    public function setDefaultPrinter(int $id, ?int $printerId): void
    {
        $device = ScannerDevice::findOrFail($id);
        $device->update(['default_printer_id' => $printerId]);

        $this->dispatch('toast', message: "Default printer updated.", type: 'success');
        unset($this->devices);
    }

    public function deactivate(int $id): void
    {
        $device = ScannerDevice::findOrFail($id);
        $device->update(['is_active' => false]);

        $this->dispatch('toast', message: "\"{$device->name}\" deactivated.", type: 'info');
        unset($this->devices);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.scanner-devices.index')
            ->layout('components.layouts.app', [
                'title'           => 'Scanner Devices',
                'breadcrumbGroup' => 'Administration',
            ]);
    }
}
