<?php

namespace App\Livewire\Inbound\Containers;

use App\Livewire\Forms\ContainerForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Create extends Component
{
    public ContainerForm $form;

    public function save(): void
    {
        if ($this->form->save()) {
            $this->dispatch('toast', message: 'Container registered successfully', type: 'success');
            $this->redirect(route('inbound.containers.index'), navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.inbound.containers.create')
            ->layout('components.layouts.app', [
                'title'           => 'Register Container',
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
