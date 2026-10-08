<?php

namespace App\Livewire\Reports;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class PrintDocument extends Component
{
    public array $grn = [
        'number'     => 'GRN-2026-042',
        'container'  => 'MSCU7654321',
        'supplier'   => 'Simba Logistics Ltd',
        'warehouse'  => 'DAR-General Store',
        'receivedAt' => '2026-10-04 09:15',
        'preparedBy' => 'Amina Juma',
    ];

    public array $lines = [
        ['sku' => 'SKU-FL-001', 'description' => 'Unga wa Ngano 2kg',   'expected' => 500, 'received' => 498, 'condition' => 'Good'],
        ['sku' => 'SKU-FL-002', 'description' => 'Unga wa Mahindi 2kg', 'expected' => 300, 'received' => 300, 'condition' => 'Good'],
        ['sku' => 'SKU-BV-010', 'description' => 'Juice 500ml 24-pack', 'expected' => 100, 'received' => 97,  'condition' => 'Damaged (3)'],
    ];

    public function mount(): void
    {
        $this->dispatch('print-ready');
    }

    public function render(): View
    {
        return view('livewire.reports.print-document')
            ->layout('components.layouts.app', [
                'title'           => 'Print Document',
                'breadcrumbGroup' => 'Reports',
            ]);
    }
}
