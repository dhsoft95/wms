<?php

namespace App\Livewire\Warehouse\Putaway;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $scanInput = '';

    // placeholder data — active task
    public array $activeTask = [
        'ref'       => 'PT-8821',
        'lpn'       => 'LPN-992014',
        'sku'       => 'SKU-AZ-001',
        'item'      => 'Flour 25kg',
        'qty'       => 40,
        'targetBin' => 'DAR-A-03-02-B',
        'step'      => 3,
        'steps'     => 3,
    ];

    // placeholder data — queue
    public array $queue = [
        ['ref' => 'PT-8821', 'lpn' => 'LPN-992014', 'sku' => 'SKU-AZ-001', 'qty' => 40,    'bin' => 'DAR-A-03-02-B', 'badge' => 'badge-blue',  'label' => 'Active'],
        ['ref' => 'PT-8822', 'lpn' => 'LPN-992015', 'sku' => 'SKU-AZ-001', 'qty' => 40,    'bin' => 'DAR-A-03-02-C', 'badge' => 'badge-slate', 'label' => 'Queued'],
        ['ref' => 'PT-8823', 'lpn' => 'LPN-992016', 'sku' => 'SKU-AZ-SUG', 'qty' => 60,    'bin' => 'DAR-B-01-04-A', 'badge' => 'badge-slate', 'label' => 'Queued'],
        ['ref' => 'PT-8824', 'lpn' => 'LPN-992017', 'sku' => 'SKU-VOD-SIM','qty' => 2000,  'bin' => 'DAR-V-02-01-A', 'badge' => 'badge-slate', 'label' => 'Queued'],
    ];

    public function confirmPutaway(): void
    {
        $bin = strtoupper(trim($this->scanInput));

        if ($bin === '') {
            $this->dispatch('toast', message: 'Scan the destination bin first', type: 'error');
            return;
        }

        if ($bin !== $this->activeTask['targetBin']) {
            $this->dispatch('toast', message: 'Bin mismatch. Expected ' . $this->activeTask['targetBin'] . ', got ' . $bin, type: 'error');
            return;
        }

        $this->dispatch('toast', message: 'Put-away confirmed for ' . $this->activeTask['lpn'], type: 'success');
        $this->scanInput = '';
    }

    public function skipTask(): void
    {
        $this->dispatch('toast', message: 'Task skipped', type: 'info');
    }

    public function render(): View
    {
        return view('livewire.warehouse.putaway.index')
            ->layout('components.layouts.app', [
                'title'           => 'Put-Away (RF Handheld)',
                'breadcrumbGroup' => 'Warehouse Operations',
            ]);
    }
}
