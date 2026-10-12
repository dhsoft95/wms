<?php

namespace App\Livewire\Admin\Warehouses;

use App\Actions\Foundation\CreateWarehouseAction;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Create extends Component
{
    public string $code      = '';
    public string $name      = '';
    public string $address   = '';
    public bool   $isActive  = true;

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->code = strtoupper($this->code);

        $this->validate([
            'code'     => ['required', 'string', 'max:10', Rule::unique('warehouses', 'code')],
            'name'     => ['required', 'string', 'max:255'],
            'address'  => ['nullable', 'string'],
            'isActive' => ['boolean'],
        ]);

        CreateWarehouseAction::execute([
            'code'      => $this->code,
            'name'      => $this->name,
            'address'   => $this->address,
            'is_active' => $this->isActive,
        ]);

        $this->dispatch('toast', message: 'Warehouse created.', type: 'success');

        $this->redirect(route('admin.warehouses.index'), navigate: true);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.warehouses.create')
            ->layout('components.layouts.app', [
                'title'           => 'New Warehouse',
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
