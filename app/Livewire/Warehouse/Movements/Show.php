<?php

declare(strict_types=1);

namespace App\Livewire\Warehouse\Movements;

use App\Enums\MovementStatus;
use App\Models\InternalMovement;
use App\Services\Warehouse\MovementWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public InternalMovement $movement;

    public function post(MovementWorkflow $workflow): void
    {
        $this->authorize('post', $this->movement);

        try {
            $this->movement = $workflow->post($this->movement, auth()->user());
            $this->dispatch('toast', message: "{$this->movement->number} posted successfully.", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function cancel(MovementWorkflow $workflow): void
    {
        $this->authorize('cancel', $this->movement);

        try {
            $this->movement = $workflow->cancel($this->movement, auth()->user());
            $this->dispatch('toast', message: "{$this->movement->number} cancelled.", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        $this->movement->load(
            'lines.item',
            'lines.fromLocation',
            'lines.toLocation',
            'lines.uom',
            'lines.serials',
            'virtualWarehouse',
            'createdBy',
        );

        return view('livewire.warehouse.movements.show')
            ->layout('components.layouts.app', [
                'title'           => $this->movement->number,
                'breadcrumbGroup' => 'Warehouse Operations',
            ]);
    }
}
