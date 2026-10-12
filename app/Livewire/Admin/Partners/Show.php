<?php

namespace App\Livewire\Admin\Partners;

use App\Models\Partner;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public Partner $partner;

    public function mount(Partner $partner): void
    {
        $this->partner = $partner;
    }

    public function render(): View
    {
        return view('livewire.admin.partners.show')
            ->layout('components.layouts.app', [
                'title'           => $this->partner->name,
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
