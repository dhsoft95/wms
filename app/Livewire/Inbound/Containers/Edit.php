<?php

declare(strict_types=1);

namespace App\Livewire\Inbound\Containers;

use App\Livewire\Forms\ContainerForm;
use App\Models\Container;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use WithFileUploads;

    public ContainerForm $form;
    public Container $container;

    public function mount(Container $container): void
    {
        $this->authorize('update', $container);
        $this->container = $container;
        $this->form->fillFrom($container);
    }

    public function save(): void
    {
        $this->authorize('update', $this->container);
        try {
            $this->form->update($this->container);
            $this->dispatch('toast', message: 'Container updated.', type: 'success');
            $this->redirect(route('inbound.containers.show', $this->container), navigate: true);
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render()
    {
        return view('livewire.inbound.containers.edit', ['container' => $this->container])
            ->layout('components.layouts.app', [
                'title'           => 'Edit Container',
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
