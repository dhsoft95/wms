<?php

namespace App\Livewire\Reports;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $dateFrom = '';
    public string $dateTo   = '';

    public array $reports = [
        ['name' => 'Stock Valuation',        'desc' => 'Current stock value by SKU and location',      'icon' => 'chart-bar',  'tag' => 'Inventory'],
        ['name' => 'Dispatch Accuracy KPI',  'desc' => 'Order fulfilment accuracy over the period',    'icon' => 'truck',      'tag' => 'Outbound'],
        ['name' => 'Cycle Count Variances',  'desc' => 'Discrepancies found during cycle counts',      'icon' => 'clipboard',  'tag' => 'Inventory'],
        ['name' => 'Receiving Summary',      'desc' => 'GRN totals, variances and condition breakdown','icon' => 'inbox',      'tag' => 'Inbound'],
        ['name' => 'Container Dwell Time',   'desc' => 'Time from arrival to clearance per container', 'icon' => 'package',    'tag' => 'Inbound'],
        ['name' => 'Stock Movement History', 'desc' => 'All putaway, transfer and pick movements',     'icon' => 'arrows',     'tag' => 'Warehouse'],
    ];

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo   = now()->format('Y-m-d');
    }

    public function runReport(string $name): void
    {
        $this->dispatch('toast', message: "Running \"{$name}\" ...", type: 'info');
    }

    public function exportCsv(string $name): void
    {
        $this->dispatch('toast', message: "CSV export for \"{$name}\" queued", type: 'success');
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
