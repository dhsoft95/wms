<?php

namespace App\Livewire\Scan;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Receive extends Component
{
    public string $barcode = '';
    public ?array $scannedItem = null;

    public function scan(): void
    {
        if (! $this->barcode) {
            return;
        }
        // TODO: look up SKU by barcode
        $this->scannedItem = [
            'sku'         => 'SKU-FL-001',
            'description' => 'Unga wa Ngano 2kg',
            'barcode'     => $this->barcode,
            'expected'    => 500,
        ];
        $this->barcode = '';
    }

    public function confirmReceive(): void
    {
        $this->scannedItem = null;
        $this->dispatch('toast', message: 'Item received and recorded.', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.scan.receive')
            ->layout('components.layouts.scan', ['title' => 'Receive']);
    }
}
