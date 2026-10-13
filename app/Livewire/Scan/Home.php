<?php
declare(strict_types=1);
namespace App\Livewire\Scan;

use App\Services\Scanning\ScannerDeviceService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Home extends Component
{
    public function mount(): void
    {
        $device = app(ScannerDeviceService::class)->resolveDevice(request(), auth()->user());
        if (! $device) {
            session()->flash('error', 'This device has been deactivated. Contact your administrator.');
            $this->redirect(route('logout'));
        }
    }

    public function render(): View
    {
        return view('livewire.scan.home')
            ->layout('components.layouts.scan', ['title' => 'Scan Home']);
    }
}
