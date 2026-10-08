<?php

namespace App\Livewire\Dashboard;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Overview extends Component
{
    public function render(): View
    {
        return view('livewire.dashboard.overview')
            ->layout('components.layouts.app', [
                'title'           => 'Operations Overview',
                'breadcrumbGroup' => 'Overview',
            ]);
    }
}
