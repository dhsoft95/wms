<?php

namespace App\Livewire\Scan;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Putaway extends Component
{
    public string $barcode = '';
    public string $binLocation = '';
    public ?array $scannedItem = null;

    public function scan(): void
    {
        if (! $this->barcode) {
            return;
        }
        $this->scannedItem = [
            'sku'         => 'SKU-FL-001',
            'description' => 'Unga wa Ngano 2kg',
            'suggested'   => 'A-12-03',
        ];
        $this->barcode = '';
    }

    public function confirmPutaway(): void
    {
        $this->validate(['binLocation' => 'required|string|max:20']);
        $this->scannedItem  = null;
        $this->binLocation  = '';
        $this->dispatch('toast', message: 'Putaway recorded.', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.scan.putaway')
            ->layout('components.layouts.scan', ['title' => 'Putaway']);
    }
}
