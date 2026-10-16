<?php

declare(strict_types=1);

namespace App\Livewire\Inbound\Containers;

use App\Livewire\Forms\ContainerForm;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    public ContainerForm $form;

    public function mount(): void
    {
        $this->authorize('create', \App\Models\Container::class);
    }

    public function save(): void
    {
        $this->authorize('create', \App\Models\Container::class);
        try {
            $container = $this->form->store(auth()->id());
            $this->dispatch('toast', message: 'Container ' . $container->number . ' registered.', type: 'success');
            $this->redirect(route('inbound.containers.show', $container), navigate: true);
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render()
    {
        return view('livewire.inbound.containers.create')
            ->layout('components.layouts.app', [
                'title'           => 'Register Container',
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
