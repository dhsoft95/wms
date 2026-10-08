<?php

namespace App\Livewire\Warehouse\Transfers;

use App\Livewire\Forms\TransferForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public TransferForm $form;

    // placeholder data
    public array $open = [
        ['ref' => 'TRF-2026-031', 'desc' => 'Tech Vault to Zanzibar',    'badge' => 'badge-amber', 'label' => 'Awaiting'],
        ['ref' => 'TRF-2026-030', 'desc' => 'General Store to Arusha',   'badge' => 'badge-blue',  'label' => 'In Transit'],
        ['ref' => 'TRF-2026-029', 'desc' => 'Tech Vault to Arusha',      'badge' => 'badge-green', 'label' => 'Received'],
    ];

    public function save(): void
    {
        if ($this->form->save()) {
            $this->dispatch('toast', message: 'Transfer request submitted for approval', type: 'success');
            $this->redirect(route('approvals.index'), navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.warehouse.transfers.index')
            ->layout('components.layouts.app', [
                'title'           => 'Department Transfers',
                'breadcrumbGroup' => 'Warehouse Operations',
            ]);
    }
}
