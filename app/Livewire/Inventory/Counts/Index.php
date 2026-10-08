<?php

namespace App\Livewire\Inventory\Counts;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    // placeholder data
    public array $rows = [
        ['bin' => 'DAR-A-01-01-A', 'sku' => 'SKU-AZ-001', 'desc' => 'Azam Bakhresa Flour 25kg', 'systemQty' => 500, 'countedQty' => 500],
        ['bin' => 'DAR-A-01-02-A', 'sku' => 'SKU-AZ-001', 'desc' => 'Azam Bakhresa Flour 25kg', 'systemQty' => 320, 'countedQty' => 318],
        ['bin' => 'DAR-A-02-01-B', 'sku' => 'SKU-AZ-SUG', 'desc' => 'Azam Sugar 1kg',           'systemQty' => 960, 'countedQty' => 960],
        ['bin' => 'DAR-A-03-02-B', 'sku' => 'SKU-AZ-001', 'desc' => 'Azam Bakhresa Flour 25kg', 'systemQty' => 500, 'countedQty' => 450],
        ['bin' => 'DAR-A-03-03-A', 'sku' => 'SKU-AZ-JCE', 'desc' => 'Azam Mango Juice 1L',      'systemQty' => 240, 'countedQty' => null],
    ];

    public function saveProgress(): void
    {
        $this->dispatch('toast', message: 'Count session saved', type: 'info');
    }

    public function submitCount(): void
    {
        $this->dispatch('toast', message: 'Count CC-2026-041 submitted for review', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.inventory.counts.index')
            ->layout('components.layouts.app', [
                'title'           => 'Cycle Count',
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
