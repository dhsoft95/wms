<?php

namespace App\Livewire\Warehouse\Movements;

use App\Livewire\Forms\MovementForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public MovementForm $form;

    // placeholder data
    public array $recent = [
        ['ref' => 'MOV-2026-141', 'from' => 'DAR-A-01-01-A', 'to' => 'DAR-A-03-02-B', 'qty' => 150],
        ['ref' => 'MOV-2026-140', 'from' => 'DAR-B-02-01-C', 'to' => 'DAR-B-01-04-A', 'qty' => 80],
        ['ref' => 'MOV-2026-139', 'from' => 'DAR-V-01-01-A', 'to' => 'DAR-V-02-01-A', 'qty' => 1000],
    ];

    public function save(): void
    {
        if ($this->form->save()) {
            $this->dispatch('toast', message: 'Movement order generated', type: 'success');
            $this->form->reset();
        }
    }

    public function render(): View
    {
        return view('livewire.warehouse.movements.index')
            ->layout('components.layouts.app', [
                'title'           => 'Internal Movements',
                'breadcrumbGroup' => 'Warehouse Operations',
            ]);
    }
}
