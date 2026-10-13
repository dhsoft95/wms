<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Labels\Printers;

use App\Models\Printer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->authorize('printers.manage');
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function printers(): Collection
    {
        return Printer::orderBy('name')->get();
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function deactivate(int $id): void
    {
        $printer = Printer::findOrFail($id);
        $printer->update(['is_active' => false]);

        $this->dispatch('toast', message: "\"{$printer->name}\" deactivated.", type: 'info');
        unset($this->printers);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.labels.printers.index')
            ->layout('components.layouts.app', [
                'title'           => 'Printers',
                'breadcrumbGroup' => 'Administration',
            ]);
    }
}
