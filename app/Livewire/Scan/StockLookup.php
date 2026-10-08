<?php

namespace App\Livewire\Scan;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class StockLookup extends Component
{
    public string $barcode = '';
    public ?array $result = null;

    public function lookup(): void
    {
        if (! $this->barcode) {
            return;
        }
        // TODO: look up real stock level
        $this->result = [
            'sku'         => 'SKU-FL-001',
            'description' => 'Unga wa Ngano 2kg',
            'barcode'     => $this->barcode,
            'on_hand'     => 480,
            'bin'         => 'A-12-03',
            'warehouse'   => 'DAR-General Store',
            'expiry'      => '2027-03-15',
        ];
        $this->barcode = '';
    }

    public function render(): View
    {
        return view('livewire.scan.stock-lookup')
            ->layout('components.layouts.scan', ['title' => 'Stock Lookup']);
    }
}
