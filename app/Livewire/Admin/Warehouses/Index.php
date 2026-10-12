<?php

namespace App\Livewire\Admin\Warehouses;

use App\Actions\Foundation\ToggleWarehouseActiveAction;
use App\Exceptions\InUseException;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public string $search               = '';
    public string $filterStatus         = '';
    public ?int   $confirmingDeactivate = null;

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function warehouses(): \Illuminate\Database\Eloquent\Collection
    {
        return Warehouse::when($this->search, function ($q) {
                $q->where(function ($q2) {
                    $q2->where('name', 'like', "%{$this->search}%")
                       ->orWhere('code', 'like', "%{$this->search}%")
                       ->orWhere('address', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterStatus !== '', function ($q) {
                $q->where('is_active', $this->filterStatus === 'active');
            })
            ->orderBy('code')
            ->get();
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function deactivate(int $id): void
    {
        $warehouse = Warehouse::findOrFail($id);

        try {
            ToggleWarehouseActiveAction::deactivate($warehouse);
        } catch (InUseException) {
            $this->dispatch('toast', message: "Cannot deactivate \"{$warehouse->name}\": it has existing locations.", type: 'error');
            $this->confirmingDeactivate = null;
            return;
        }

        $this->dispatch('toast', message: "\"{$warehouse->name}\" deactivated.", type: 'info');
        $this->confirmingDeactivate = null;
        unset($this->warehouses);
    }

    public function activate(int $id): void
    {
        $warehouse = Warehouse::findOrFail($id);
        ToggleWarehouseActiveAction::activate($warehouse);
        $this->dispatch('toast', message: "\"{$warehouse->name}\" activated.", type: 'success');
        unset($this->warehouses);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.warehouses.index')
            ->layout('components.layouts.app', [
                'title'           => 'Warehouses',
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
