<?php

namespace App\Livewire\Admin\Skus;

use App\Livewire\Forms\SkuForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public SkuForm $form;

    public array $recentSkus = [
        ['code' => 'SKU-FL-001', 'name' => 'Unga wa Ngano 2kg',   'category' => 'Flour',     'uom' => 'Bag',  'client' => 'Azam Ltd'],
        ['code' => 'SKU-FL-002', 'name' => 'Unga wa Mahindi 2kg', 'category' => 'Flour',     'uom' => 'Bag',  'client' => 'Azam Ltd'],
        ['code' => 'SKU-BV-010', 'name' => 'Juice 500ml 24-pack', 'category' => 'Beverages', 'uom' => 'Case', 'client' => 'Azam Ltd'],
        ['code' => 'SKU-TE-022', 'name' => 'SIM Card Retail Pack', 'category' => 'Telecoms', 'uom' => 'Pack', 'client' => 'Vodacom'],
    ];

    public function save(): void
    {
        if ($this->form->save()) {
            $this->dispatch('toast', message: 'SKU ' . $this->form->skuCode . ' created', type: 'success');
            $this->form->reset();
        }
    }

    public function render(): View
    {
        return view('livewire.admin.skus.index')
            ->layout('components.layouts.app', [
                'title'           => 'SKU Master',
                'breadcrumbGroup' => 'Admin',
            ]);
    }
}
