<?php

namespace App\Livewire\Scan;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Pick extends Component
{
    public string $orderRef = '';
    public ?array $pickList = null;

    public function loadOrder(): void
    {
        if (! $this->orderRef) {
            return;
        }
        // TODO: load real pick list
        $this->pickList = [
            ['sku' => 'SKU-FL-001', 'description' => 'Unga wa Ngano 2kg',   'qty' => 20, 'bin' => 'A-12-03', 'picked' => false],
            ['sku' => 'SKU-FL-002', 'description' => 'Unga wa Mahindi 2kg', 'qty' => 10, 'bin' => 'B-04-01', 'picked' => false],
        ];
    }

    public function markPicked(int $index): void
    {
        if (isset($this->pickList[$index])) {
            $this->pickList[$index]['picked'] = true;
        }

        if (collect($this->pickList)->every(fn ($l) => $l['picked'])) {
            $this->dispatch('toast', message: 'All items picked for ' . $this->orderRef, type: 'success');
            $this->pickList = null;
            $this->orderRef = '';
        }
    }

    public function render(): View
    {
        return view('livewire.scan.pick')
            ->layout('components.layouts.scan', ['title' => 'Pick']);
    }
}
