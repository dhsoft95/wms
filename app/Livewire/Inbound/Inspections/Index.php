<?php

namespace App\Livewire\Inbound\Inspections;

use App\Livewire\Forms\InspectionForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public InspectionForm $form;

    // placeholder data
    public array $recent = [
        ['ref' => 'INS-2026-022', 'type' => 'Shortage',   'grn' => 'GRN-2026-089', 'badge' => 'badge-amber', 'label' => 'Pending'],
        ['ref' => 'INS-2026-021', 'type' => 'Damaged',    'grn' => 'GRN-2026-087', 'badge' => 'badge-green', 'label' => 'Resolved'],
        ['ref' => 'INS-2026-020', 'type' => 'Wrong item', 'grn' => 'GRN-2026-085', 'badge' => 'badge-green', 'label' => 'Resolved'],
    ];

    public function save(): void
    {
        if ($this->form->save()) {
            $this->dispatch('toast', message: 'Inspection report saved and sent for approval', type: 'success');
            $this->form->reset();
        }
    }

    public function render(): View
    {
        return view('livewire.inbound.inspections.index')
            ->layout('components.layouts.app', [
                'title'           => 'Inspections & Discrepancies',
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
