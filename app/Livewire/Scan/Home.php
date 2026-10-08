<?php

namespace App\Livewire\Scan;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Home extends Component
{
    public function render(): View
    {
        return view('livewire.scan.home')
            ->layout('components.layouts.scan', ['title' => 'Scan Home']);
    }
}
