<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Labels\Templates;

use App\Enums\LabelTemplateType;
use App\Models\LabelTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Enum;
use Livewire\Component;

class Create extends Component
{
    public string $name      = '';
    public string $type      = 'pallet_lpn';
    public string $widthMm   = '100';
    public string $heightMm  = '150';
    public string $dpi       = '203';
    public bool   $isActive  = true;

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->authorize('labels.templates.manage');
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->validate([
            'name'      => ['required', 'string', 'max:255'],
            'type'      => ['required', new Enum(LabelTemplateType::class)],
            'widthMm'   => ['required', 'numeric', 'min:10', 'max:500'],
            'heightMm'  => ['required', 'numeric', 'min:10', 'max:500'],
            'dpi'       => ['required', 'in:203,300'],
            'isActive'  => ['boolean'],
        ]);

        $template = LabelTemplate::create([
            'name'       => $this->name,
            'type'       => $this->type,
            'width_mm'   => (float) $this->widthMm,
            'height_mm'  => (float) $this->heightMm,
            'dpi'        => (int) $this->dpi,
            'elements'   => [],
            'is_default' => false,
            'is_active'  => $this->isActive,
        ]);

        $this->dispatch('toast', message: 'Label template created.', type: 'success');

        $this->redirect(route('admin.labels.templates.edit', $template), navigate: true);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.labels.templates.create', [
            'templateTypes' => LabelTemplateType::cases(),
        ])
            ->layout('components.layouts.app', [
                'title'           => 'New Label Template',
                'breadcrumbGroup' => 'Administration',
            ]);
    }
}
