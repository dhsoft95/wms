<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Jobs\GenerateReportJob;
use App\Models\ReportRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public string $dateFrom = '';
    public string $dateTo   = '';

    public array $catalogue = [
        ['type' => 'stock-valuation',        'name' => 'Stock Valuation',        'desc' => 'Current stock value by SKU and location',       'icon' => 'chart-bar',  'tag' => 'Inventory'],
        ['type' => 'dispatch-accuracy',      'name' => 'Dispatch Accuracy KPI',  'desc' => 'Order fulfilment accuracy over the period',     'icon' => 'truck',      'tag' => 'Outbound'],
        ['type' => 'cycle-count-variances',  'name' => 'Cycle Count Variances',  'desc' => 'Discrepancies found during cycle counts',        'icon' => 'clipboard',  'tag' => 'Inventory'],
        ['type' => 'receiving-summary',      'name' => 'Receiving Summary',      'desc' => 'GRN totals, variances and condition breakdown',  'icon' => 'inbox',      'tag' => 'Inbound'],
        ['type' => 'stock-aging',            'name' => 'Stock Aging',            'desc' => 'Age buckets and expiry profile for held stock',  'icon' => 'package',    'tag' => 'Inventory'],
        ['type' => 'stock-movement-history', 'name' => 'Stock Movement History', 'desc' => 'All putaway, transfer and pick movements',       'icon' => 'arrows',     'tag' => 'Warehouse'],
    ];

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo   = now()->format('Y-m-d');
    }

    #[Computed]
    public function requests(): Collection
    {
        return ReportRequest::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->keyBy('type');
    }

    #[Computed]
    public function history(): Collection
    {
        return ReportRequest::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();
    }

    public function hasRunning(): bool
    {
        return $this->requests->whereIn('status', ['pending', 'processing'])->isNotEmpty();
    }

    public function queueReport(string $type): void
    {
        $request = ReportRequest::create([
            'user_id'   => auth()->id(),
            'type'      => $type,
            'date_from' => $this->dateFrom ?: now()->startOfMonth()->format('Y-m-d'),
            'date_to'   => $this->dateTo   ?: now()->format('Y-m-d'),
            'status'    => 'pending',
        ]);

        GenerateReportJob::dispatch($request)->onQueue('default');

        unset($this->requests);
        $this->dispatch('toast', message: 'Report queued — it will be ready in a few seconds.', type: 'info');
    }

    public function refresh(): void
    {
        unset($this->requests);
        unset($this->history);
    }

    public function render(): View
    {
        return view('livewire.reports.index')
            ->layout('components.layouts.app', [
                'title'           => 'Reports',
                'breadcrumbGroup' => 'Reports',
            ]);
    }
}
