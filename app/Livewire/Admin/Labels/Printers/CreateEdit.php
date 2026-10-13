<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Labels\Printers;

use App\Enums\PrinterKind;
use App\Models\Printer;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Enum;
use Livewire\Component;

class CreateEdit extends Component
{
    public ?Printer $printer = null;

    public string $name         = '';
    public string $kind         = 'pdf_browser';
    public string $ip           = '';
    public string $port         = '9100';
    public string $dpi          = '203';
    public string $locationNote = '';
    public bool   $isActive     = true;

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(?Printer $printer = null): void
    {
        $this->authorize('printers.manage');

        if ($printer && $printer->exists) {
            $this->printer      = $printer;
            $this->name         = $printer->name;
            $this->kind         = $printer->kind->value;
            $this->ip           = $printer->ip ?? '';
            $this->port         = (string) ($printer->port ?? '9100');
            $this->dpi          = (string) $printer->dpi;
            $this->locationNote = $printer->location_note ?? '';
            $this->isActive     = $printer->is_active;
        }
    }

    // ─── Computed helpers ─────────────────────────────────────────────────────

    public function isNetworkPrinter(): bool
    {
        return $this->kind === 'zpl_network';
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function save(): void
    {
        $rules = [
            'name'         => ['required', 'string', 'max:255'],
            'kind'         => ['required', new Enum(PrinterKind::class)],
            'dpi'          => ['required', 'in:203,300'],
            'locationNote' => ['nullable', 'string', 'max:255'],
            'isActive'     => ['boolean'],
        ];

        if ($this->isNetworkPrinter()) {
            $rules['ip']   = ['required', 'string', 'max:45'];
            $rules['port'] = ['required', 'integer', 'min:1', 'max:65535'];
        }

        $this->validate($rules);

        $data = [
            'name'          => $this->name,
            'kind'          => $this->kind,
            'ip'            => $this->isNetworkPrinter() ? $this->ip : null,
            'port'          => $this->isNetworkPrinter() ? (int) $this->port : null,
            'dpi'           => (int) $this->dpi,
            'location_note' => $this->locationNote ?: null,
            'is_active'     => $this->isActive,
        ];

        if ($this->printer && $this->printer->exists) {
            $this->printer->update($data);
            $this->dispatch('toast', message: 'Printer updated.', type: 'success');
        } else {
            Printer::create($data);
            $this->dispatch('toast', message: 'Printer created.', type: 'success');
        }

        $this->redirect(route('admin.printers.index'), navigate: true);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        $isEdit = $this->printer && $this->printer->exists;

        return view('livewire.admin.labels.printers.create-edit', [
            'printerKinds' => PrinterKind::cases(),
            'isEdit'       => $isEdit,
        ])
            ->layout('components.layouts.app', [
                'title'           => $isEdit ? 'Edit Printer' : 'New Printer',
                'breadcrumbGroup' => 'Administration',
            ]);
    }
}
