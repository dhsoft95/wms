<?php

namespace App\Livewire\Admin\Roles;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Index extends Component
{
    public ?string $editingRole = null;

    /** @var array<int, string> */
    public array $selectedPermissions = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('roles.manage'), 403);
    }

    // ─── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function roles(): \Illuminate\Database\Eloquent\Collection
    {
        return Role::withCount('users')->with('permissions')->orderBy('name')->get();
    }

    /** @return array<string, array<int, string>> */
    #[Computed]
    public function permissionsGrouped(): array
    {
        return Permission::orderBy('name')
            ->get()
            ->groupBy(fn (Permission $p) => str_contains($p->name, '.')
                ? explode('.', $p->name)[0]
                : 'general'
            )
            ->map(fn ($perms) => $perms->pluck('name')->all())
            ->all();
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function startEdit(string $roleName): void
    {
        if ($roleName === 'system-admin') {
            $this->dispatch('toast', message: 'system-admin cannot be edited.', type: 'error');
            return;
        }

        $role = Role::where('name', $roleName)->firstOrFail();

        $this->editingRole          = $roleName;
        $this->selectedPermissions  = $role->permissions->pluck('name')->all();
    }

    public function savePermissions(): void
    {
        if ($this->editingRole === 'system-admin') {
            $this->dispatch('toast', message: 'system-admin permissions cannot be changed.', type: 'error');
            return;
        }

        $this->validate([
            'editingRole'         => 'required|string',
            'selectedPermissions' => 'array',
            'selectedPermissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::where('name', $this->editingRole)->firstOrFail();
        $role->syncPermissions($this->selectedPermissions);

        $this->dispatch('toast', message: "Permissions saved for \"{$this->editingRole}\".", type: 'success');
        $this->cancelEdit();
    }

    public function cancelEdit(): void
    {
        $this->editingRole         = null;
        $this->selectedPermissions = [];
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.roles.index')
            ->layout('components.layouts.app', [
                'title'           => 'Roles & Permissions',
                'breadcrumbGroup' => 'Admin',
            ]);
    }
}
