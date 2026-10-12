<?php

namespace App\Livewire\Outbound\Orders;

use App\Livewire\Forms\OrderForm;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Create extends Component
{
    public OrderForm $form;

    public array $lines = [
        ['sku_code' => 'SKU-AZ-001', 'description' => 'Azam Bakhresa Flour 25kg', 'quantity' => 120],
    ];

    public function mount(): void
    {
        $this->form->orderReference = 'ORD-2026-102';
    }

    public function addLine(): void
    {
        $this->lines[] = ['sku_code' => '', 'description' => '', 'quantity' => 1];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    #[Computed]
    public function totalQty(): int
    {
        return (int) array_sum(array_column($this->lines, 'quantity'));
    }

    #[Computed]
    public function lineCount(): int
    {
        return count($this->lines);
    }

    public function saveDraft(): void
    {
        $this->dispatch('toast', message: 'Order saved as draft', type: 'info');
    }

    public function save(): void
    {
        if ($this->form->save()) {
            // TODO: persist lines
            $this->dispatch('toast', message: $this->form->orderReference . ' created and queued for allocation', type: 'success');
            $this->redirect(route('outbound.dispatch.index'), navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.outbound.orders.create', [
            'lineCount' => $this->lineCount,
            'totalQty'  => $this->totalQty,
        ])->layout('components.layouts.app', [
            'title'           => 'New Customer Order',
            'breadcrumbGroup' => 'Outbound',
        ]);
    }
}
