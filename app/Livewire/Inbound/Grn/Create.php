<?php

namespace App\Livewire\Inbound\Grn;

use App\Livewire\Forms\GrnForm;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Create extends Component
{
    public GrnForm $form;

    public array $lines = [
        ['sku_code' => 'SKU-AZ-001', 'expected_qty' => 500, 'received_qty' => 488, 'batch_number' => 'BATCH-9021'],
        ['sku_code' => 'SKU-AZ-SUG', 'expected_qty' => 300, 'received_qty' => 300, 'batch_number' => 'BATCH-9022'],
    ];

    public function mount(): void
    {
        $this->form->grnNumber = 'GRN-2026-090';
        $this->form->receivedAt = now()->format('Y-m-d\TH:i');
    }

    public function addLine(): void
    {
        $this->lines[] = ['sku_code' => '', 'expected_qty' => 0, 'received_qty' => 0, 'batch_number' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function getExpectedTotalProperty(): int
    {
        return (int) array_sum(array_column($this->lines, 'expected_qty'));
    }

    public function getReceivedTotalProperty(): int
    {
        return (int) array_sum(array_column($this->lines, 'received_qty'));
    }

    public function getVarianceTotalProperty(): int
    {
        return $this->receivedTotal - $this->expectedTotal;
    }

    public function saveDraft(): void
    {
        $this->dispatch('toast', message: 'GRN saved as draft', type: 'info');
    }

    public function save(): void
    {
        if ($this->form->save()) {
            // TODO: persist lines
            $this->dispatch('toast', message: $this->form->grnNumber . ' submitted successfully', type: 'success');
            $this->redirect(route('inbound.grn.index'), navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.inbound.grn.create')
            ->layout('components.layouts.app', [
                'title'           => 'New Goods Receipt',
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
