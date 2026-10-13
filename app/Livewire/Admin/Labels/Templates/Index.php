<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Labels\Templates;

use App\Enums\LabelTemplateType;
use App\Models\LabelTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $filterType   = '';
    public string $filterActive = '1';

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->authorize('labels.templates.manage');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function updatingFilterActive(): void
    {
        $this->resetPage();
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function templates(): LengthAwarePaginator
    {
        return LabelTemplate::when($this->search, function ($q) {
                $q->where('name', 'like', "%{$this->search}%");
            })
            ->when($this->filterType !== '', function ($q) {
                $q->where('type', $this->filterType);
            })
            ->when($this->filterActive !== '', function ($q) {
                $q->where('is_active', $this->filterActive === '1');
            })
            ->orderBy('name')
            ->paginate(20);
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function setDefault(int $id): void
    {
        $template = LabelTemplate::findOrFail($id);

        // Unset others of the same type
        LabelTemplate::where('type', $template->type)
            ->where('id', '!=', $id)
            ->update(['is_default' => false]);

        $template->update(['is_default' => true]);

        $this->dispatch('toast', message: "\"{$template->name}\" set as default.", type: 'success');
        unset($this->templates);
    }

    public function deactivate(int $id): void
    {
        $template = LabelTemplate::findOrFail($id);
        $template->update(['is_active' => false]);

        $this->dispatch('toast', message: "\"{$template->name}\" deactivated.", type: 'info');
        unset($this->templates);
    }

    public function duplicate(int $id): void
    {
        $template = LabelTemplate::findOrFail($id);

        LabelTemplate::create([
            'name'      => "Copy of {$template->name}",
            'type'      => $template->type,
            'width_mm'  => $template->width_mm,
            'height_mm' => $template->height_mm,
            'dpi'       => $template->dpi,
            'elements'  => $template->elements,
            'is_default'=> false,
            'is_active' => $template->is_active,
        ]);

        $this->dispatch('toast', message: "Duplicated \"{$template->name}\".", type: 'success');
        unset($this->templates);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.labels.templates.index')
            ->layout('components.layouts.app', [
                'title'           => 'Label Templates',
                'breadcrumbGroup' => 'Administration',
            ]);
    }
}
