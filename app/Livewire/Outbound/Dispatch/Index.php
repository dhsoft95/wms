<?php

namespace App\Livewire\Outbound\Dispatch;

use App\Livewire\Forms\DispatchForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public DispatchForm $form;

    public function mount(): void
    {
        $this->form->gatePassNumber = 'GP-2026-089';
        $this->form->departureTime = now()->format('Y-m-d\TH:i');
    }

    public function save(): void
    {
        if ($this->form->save()) {
            $this->dispatch('toast', message: 'Gate pass ' . $this->form->gatePassNumber . ' issued, vehicle released', type: 'success');
            $this->redirect(route('outbound.pod.index'), navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.outbound.dispatch.index')
            ->layout('components.layouts.app', [
                'title'           => 'Dispatch & Gate Release',
                'breadcrumbGroup' => 'Outbound',
            ]);
    }
}
