<?php

namespace App\Livewire\Inventory\Adjustments;

use App\Livewire\Forms\AdjustmentForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public AdjustmentForm $form;

    public function getVarianceProperty(): int
    {
        return $this->form->physicalCount - $this->form->systemQuantity;
    }

    public function save(): void
    {
        if ($this->form->save()) {
            $this->dispatch('toast', message: 'Adjustment submitted for approval', type: 'success');
            $this->redirect(route('approvals.index'), navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.inventory.adjustments.index')
            ->layout('components.layouts.app', [
                'title'           => 'Stock Adjustments',
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
