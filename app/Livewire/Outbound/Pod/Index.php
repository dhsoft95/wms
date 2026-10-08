<?php

namespace App\Livewire\Outbound\Pod;

use App\Livewire\Forms\PodForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public PodForm $form;

    // placeholder data
    public array $awaiting = [
        ['ref' => 'GP-2026-088', 'client' => 'Azam Ltd',  'location' => 'Mikocheni', 'age' => 'Today',     'ageColor' => ''],
        ['ref' => 'GP-2026-087', 'client' => 'Vodacom',   'location' => 'Mlimani City', 'age' => 'Yesterday', 'ageColor' => ''],
        ['ref' => 'GP-2026-085', 'client' => 'Azam Ltd',  'location' => 'Kibaha',    'age' => '2 days',    'ageColor' => 'text-red-600 font-semibold'],
    ];

    public function save(): void
    {
        if ($this->form->save()) {
            $this->dispatch('toast', message: 'Delivery confirmed and POD uploaded', type: 'success');
            $this->form->reset();
        }
    }

    public function render(): View
    {
        return view('livewire.outbound.pod.index')
            ->layout('components.layouts.app', [
                'title'           => 'Proof of Delivery',
                'breadcrumbGroup' => 'Outbound',
            ]);
    }
}
