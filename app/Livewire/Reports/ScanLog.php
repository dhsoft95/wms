<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\ScanEvent;
use App\Models\ScannerDevice;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ScanLog extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $dateFrom = '';

    #[Url(except: '')]
    public string $dateTo = '';

    #[Url(except: '')]
    public string $userId = '';

    #[Url(except: '')]
    public string $deviceId = '';

    #[Url(except: '')]
    public string $success = '';  // '' | '1' | '0'

    public function mount(): void
    {
        $this->authorize('reports.view');

        if ($this->dateFrom === '') {
            $this->dateFrom = now()->startOfDay()->format('Y-m-d');
        }
        if ($this->dateTo === '') {
            $this->dateTo = now()->format('Y-m-d');
        }
    }

    public function updatedDateFrom(): void  { $this->resetPage(); }
    public function updatedDateTo(): void    { $this->resetPage(); }
    public function updatedUserId(): void    { $this->resetPage(); }
    public function updatedDeviceId(): void  { $this->resetPage(); }
    public function updatedSuccess(): void   { $this->resetPage(); }

    #[Computed]
    public function events(): LengthAwarePaginator
    {
        $query = ScanEvent::with(['user', 'scannerDevice'])
            ->whereBetween('created_at', [
                $this->dateFrom . ' 00:00:00',
                $this->dateTo   . ' 23:59:59',
            ]);

        if ($this->userId !== '') {
            $query->where('user_id', $this->userId);
        }

        if ($this->deviceId !== '') {
            $query->where('scanner_device_id', $this->deviceId);
        }

        if ($this->success !== '') {
            $query->where('success', (bool) $this->success);
        }

        return $query->orderByDesc('created_at')->paginate(50);
    }

    #[Computed]
    public function users(): \Illuminate\Database\Eloquent\Collection
    {
        return User::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function devices(): \Illuminate\Database\Eloquent\Collection
    {
        return ScannerDevice::orderBy('code')->get(['id', 'code', 'name']);
    }

    public function render(): View
    {
        return view('livewire.reports.scan-log')
            ->layout('components.layouts.app', [
                'title'           => 'Scan Log',
                'breadcrumbGroup' => 'Reports',
            ]);
    }
}
