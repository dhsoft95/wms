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

    public bool   $showCreateModal       = false;
    public string $newRoleName           = '';
    public array  $newRolePermissions    = [];

    public ?string $confirmDeleteRole    = null;

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

    public function openCreate(): void
    {
        $this->newRoleName        = '';
        $this->newRolePermissions = [];
        $this->showCreateModal    = true;
    }

    public function closeCreate(): void
    {
        $this->showCreateModal    = false;
        $this->newRoleName        = '';
        $this->newRolePermissions = [];
    }

    public function createRole(): void
    {
        $this->validate([
            'newRoleName' => [
                'required', 'string', 'max:64',
                'regex:/^[a-z0-9\-]+$/',
                'unique:roles,name',
            ],
            'newRolePermissions'   => 'array',
            'newRolePermissions.*' => 'string|exists:permissions,name',
        ], [
            'newRoleName.regex'  => 'Role name must be lowercase letters, numbers, and hyphens only.',
            'newRoleName.unique' => 'A role with this name already exists.',
        ]);

        $role = Role::create(['name' => $this->newRoleName, 'guard_name' => 'web']);

        if (! empty($this->newRolePermissions)) {
            $role->syncPermissions($this->newRolePermissions);
        }

        unset($this->roles);
        $this->dispatch('toast', message: "Role \"{$this->newRoleName}\" created.", type: 'success');
        $this->closeCreate();
    }

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

    public function confirmDelete(string $roleName): void
    {
        if ($roleName === 'system-admin') {
            $this->dispatch('toast', message: 'system-admin cannot be deleted.', type: 'error');
            return;
        }

        $this->confirmDeleteRole = $roleName;
    }

    public function deleteRole(): void
    {
        if (! $this->confirmDeleteRole || $this->confirmDeleteRole === 'system-admin') {
            return;
        }

        $role = Role::where('name', $this->confirmDeleteRole)->first();

        if ($role) {
            if ($role->users()->count() > 0) {
                $this->dispatch('toast', message: "Cannot delete \"{$this->confirmDeleteRole}\" — it has assigned users.", type: 'error');
                $this->confirmDeleteRole = null;
                return;
            }

            $role->delete();
        }

        $deleted = $this->confirmDeleteRole;
        $this->confirmDeleteRole = null;
        unset($this->roles);
        $this->dispatch('toast', message: "Role \"{$deleted}\" deleted.", type: 'success');
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteRole = null;
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
