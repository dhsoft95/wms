<?php

namespace App\Livewire\Inventory\Stock;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';
    public string $warehouseFilter = '';

    // placeholder data
    protected array $allRows = [
        ['sku' => 'SKU-AZ-001',  'desc' => 'Azam Bakhresa Flour 25kg',     'cat' => 'Consumer Goods', 'warehouse' => 'DAR-General Store', 'bin' => 'DAR-A-03-02-B', 'total' => 4500,  'allocated' => 1200, 'available' => 3300, 'badge' => 'badge-green', 'label' => 'In Stock'],
        ['sku' => 'SKU-AZ-SUG',  'desc' => 'Azam Sugar 1kg',               'cat' => 'Consumer Goods', 'warehouse' => 'DAR-General Store', 'bin' => 'DAR-B-01-04-A', 'total' => 2160,  'allocated' => 1900, 'available' => 260,  'badge' => 'badge-red',   'label' => 'Low Stock'],
        ['sku' => 'SKU-VOD-SIM', 'desc' => 'Vodacom 4G LTE SIM Starter Pack','cat' => 'Telecommunications','warehouse' => 'DAR-Tech Vault','bin' => 'DAR-V-02-01-A', 'total' => 12800, 'allocated' => 3500, 'available' => 9300, 'badge' => 'badge-green', 'label' => 'In Stock'],
        ['sku' => 'SKU-VOD-RTR', 'desc' => 'Vodacom 4G Home Router',        'cat' => 'Telecommunications','warehouse' => 'DAR-Tech Vault','bin' => 'DAR-V-03-02-B', 'total' => 640,   'allocated' => 120,  'available' => 520,  'badge' => 'badge-green', 'label' => 'In Stock'],
        ['sku' => 'SKU-AZ-JCE',  'desc' => 'Azam Mango Juice 1L',          'cat' => 'Consumer Goods', 'warehouse' => 'Cold Room',         'bin' => 'DAR-C-01-01-A', 'total' => 1800,  'allocated' => 600,  'available' => 1200, 'badge' => 'badge-amber', 'label' => 'Expiring Soon'],
    ];

    public function filteredRows(): array
    {
        return array_values(array_filter($this->allRows, function ($r) {
            $matchesWarehouse = $this->warehouseFilter === '' || $r['warehouse'] === $this->warehouseFilter;
            $matchesSearch = $this->search === '' || str_contains(strtolower($r['sku'] . $r['desc'] . $r['bin']), strtolower($this->search));
            return $matchesWarehouse && $matchesSearch;
        }));
    }

    public function export(): void
    {
        $this->dispatch('toast', message: 'Stock ledger exported to CSV', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.inventory.stock.index', [
            'rows' => $this->filteredRows(),
        ])->layout('components.layouts.app', [
            'title'           => 'Stock on Hand',
            'breadcrumbGroup' => 'Inventory Control',
        ]);
    }
}
