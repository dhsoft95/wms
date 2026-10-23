<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\Counts;

use App\Enums\CycleCountStatus;
use App\Models\CycleCount;
use App\Models\CycleCountLine;
use App\Services\Inventory\CycleCountWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public CycleCount $count;
    // keyed by line id → counted_qty input (blind: system_qty never sent to frontend)
    public array $entries = [];

    public function mount(): void
    {
        $this->count->load('lines.item', 'lines.location', 'lines.batch', 'lines.uom');
        foreach ($this->count->lines as $line) {
            $this->entries[$line->id] = $line->counted_qty !== null ? (string) $line->counted_qty : '';
        }
    }

    public function saveEntry(int $lineId, CycleCountWorkflow $workflow): void
    {
        $this->authorize('recordCount', $this->count);

        $qty = $this->entries[$lineId] ?? '';
        if (! is_numeric($qty) || (float) $qty < 0) {
            $this->dispatch('toast', message: 'Enter a valid non-negative quantity', type: 'error');
            return;
        }

        try {
            $line = CycleCountLine::findOrFail($lineId);
            $workflow->recordCount($line, $qty, auth()->user());
            $this->count->refresh();
            $this->dispatch('toast', message: 'Entry saved', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function submitForReview(CycleCountWorkflow $workflow): void
    {
        $this->authorize('recordCount', $this->count);

        try {
            $this->count = $workflow->submitForReview($this->count, auth()->user());
            $this->dispatch('toast', message: 'Count submitted for variance review', type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function post(CycleCountWorkflow $workflow): void
    {
        $this->authorize('approve', $this->count);

        try {
            $this->count = $workflow->post($this->count, auth()->user());
            $this->dispatch('toast', message: "Count {$this->count->number} posted — variances adjusted", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function cancel(CycleCountWorkflow $workflow): void
    {
        $this->authorize('approve', $this->count);

        try {
            $this->count = $workflow->cancel($this->count, auth()->user());
            $this->dispatch('toast', message: "Count {$this->count->number} cancelled.", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render(): View
    {
        $this->count->load('lines.item', 'lines.location', 'lines.batch', 'lines.uom', 'createdBy', 'virtualWarehouse');

        // Blind count: hide system_qty from the view when status is Open or Counting
        $blind = in_array($this->count->status, [CycleCountStatus::Open, CycleCountStatus::Counting], true);

        return view('livewire.inventory.counts.show', compact('blind'))
            ->layout('components.layouts.app', [
                'title'           => $this->count->number,
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
