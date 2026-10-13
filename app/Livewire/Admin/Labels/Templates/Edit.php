<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Labels\Templates;

use App\Enums\LabelTemplateType;
use App\Models\LabelTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Enum;
use Livewire\Component;

class Edit extends Component
{
    public LabelTemplate $template;

    public string $name     = '';
    public string $type     = '';
    public string $widthMm  = '';
    public string $heightMm = '';
    public string $dpi      = '';
    public bool   $isActive = true;
    public array  $elements = [];

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(LabelTemplate $template): void
    {
        $this->authorize('labels.templates.manage');

        $this->template  = $template;
        $this->name      = $template->name;
        $this->type      = $template->type->value;
        $this->widthMm   = (string) $template->width_mm;
        $this->heightMm  = (string) $template->height_mm;
        $this->dpi       = (string) $template->dpi;
        $this->isActive  = $template->is_active;
        $this->elements  = $template->elements ?? [];
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

        $this->template->update([
            'name'      => $this->name,
            'type'      => $this->type,
            'width_mm'  => (float) $this->widthMm,
            'height_mm' => (float) $this->heightMm,
            'dpi'       => (int) $this->dpi,
            'is_active' => $this->isActive,
            'elements'  => $this->elements,
        ]);

        $this->dispatch('toast', message: 'Template saved.', type: 'success');
    }

    public function addElement(string $kind): void
    {
        $defaults = match($kind) {
            'text'    => ['kind' => 'text', 'x_mm' => 5, 'y_mm' => 5, 'w_mm' => 50, 'h_mm' => 8, 'value' => '', 'font_size_pt' => 10, 'bold' => false, 'align' => 'left'],
            'field'   => ['kind' => 'field', 'x_mm' => 5, 'y_mm' => 5, 'w_mm' => 50, 'h_mm' => 8, 'field_key' => '', 'font_size_pt' => 10, 'bold' => false, 'align' => 'left'],
            'barcode' => ['kind' => 'barcode', 'x_mm' => 5, 'y_mm' => 5, 'w_mm' => 40, 'h_mm' => 20, 'field_key' => '', 'symbology' => 'code128', 'show_text' => true],
            'line'    => ['kind' => 'line', 'x_mm' => 5, 'y_mm' => 5, 'w_mm' => 50, 'h_mm' => 1, 'thickness_mm' => 0.5],
            'box'     => ['kind' => 'box', 'x_mm' => 5, 'y_mm' => 5, 'w_mm' => 50, 'h_mm' => 20, 'thickness_mm' => 0.5],
            default   => ['kind' => $kind, 'x_mm' => 5, 'y_mm' => 5, 'w_mm' => 30, 'h_mm' => 10],
        };

        $this->elements[] = $defaults;
    }

    public function removeElement(int $idx): void
    {
        unset($this->elements[$idx]);
        $this->elements = array_values($this->elements);
    }

    public function updateElement(int $idx, array $props): void
    {
        if (isset($this->elements[$idx])) {
            $this->elements[$idx] = array_merge($this->elements[$idx], $props);
        }
    }

    public function previewZpl(): void
    {
        // Placeholder — real implementation would call a ZPL renderer service
        $this->dispatch('toast', message: 'ZPL preview is not yet configured.', type: 'info');
    }

    public function availableFields(): array
    {
        if (empty($this->type)) {
            return [];
        }

        $enumType = LabelTemplateType::tryFrom($this->type);

        if ($enumType === null) {
            return [];
        }

        return LabelTemplate::availableFields($enumType);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.labels.templates.edit', [
            'templateTypes'   => LabelTemplateType::cases(),
            'availableFields' => $this->availableFields(),
        ])
            ->layout('components.layouts.app', [
                'title'           => 'Edit Label Template',
                'breadcrumbGroup' => 'Administration',
            ]);
    }
}
