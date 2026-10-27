<div class="space-y-6">
    {{-- Page header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Roles &amp; Permissions</h2>
            <p class="page-sub">Assign permissions to each role. The system-admin role bypasses all checks via <code class="mono">Gate::before</code>.</p>
        </div>
        <button type="button" wire:click="openCreate" class="btn btn-primary">
            <x-ui.icon name="plus" class="w-4 h-4" /> Add Role
        </button>
    </div>

    {{-- Create Role Modal --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm"
             x-data wire:click.self="closeCreate">
            <div class="card w-full max-w-lg p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white">New Role</h3>
                    <button wire:click="closeCreate" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>

                <div>
                    <label class="label">Role Name <span class="required-mark">*</span></label>
                    <input wire:model="newRoleName"
                           type="text"
                           placeholder="e.g. warehouse-supervisor"
                           class="input font-mono @error('newRoleName') border-red-400 @enderror"
                           autofocus>
                    <p class="hint mt-1">Lowercase letters, numbers and hyphens only. Cannot be changed later.</p>
                    @error('newRoleName') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>

                {{-- Permissions --}}
                <div>
                    <label class="label mb-2">Permissions
                        <span class="text-xs font-normal text-slate-400 ml-1">(optional — can be set later)</span>
                    </label>
                    <div class="max-h-64 overflow-y-auto space-y-4 pr-1">
                        @foreach ($this->permissionsGrouped as $module => $permissions)
                            <div>
                                <p class="section-label mb-1.5 capitalize">{{ str_replace(['-', '_'], ' ', $module) }}</p>
                                <div class="grid grid-cols-2 gap-1.5">
                                    @foreach ($permissions as $permission)
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox"
                                                   wire:model="newRolePermissions"
                                                   value="{{ $permission }}"
                                                   class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800">
                                            <span class="text-xs mono text-slate-700 dark:text-slate-300 truncate"
                                                  title="{{ $permission }}">
                                                {{ Str::after($permission, '.') }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('newRolePermissions') <p class="hint hint-error mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-2 pt-1 border-t border-slate-100 dark:border-slate-800">
                    <span class="text-xs text-slate-400 self-center mr-auto">
                        {{ count($newRolePermissions) }} permission(s) selected
                    </span>
                    <button type="button" wire:click="closeCreate" class="btn btn-ghost">Cancel</button>
                    <button type="button" wire:click="createRole" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading.remove wire:target="createRole">
                            <x-ui.icon name="check" class="w-4 h-4" /> Create Role
                        </span>
                        <span wire:loading wire:target="createRole">Creating…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Roles table --}}
    <div class="card overflow-hidden">
        <div class="card-header">
            <h3 class="card-title">Roles</h3>
        </div>
        <x-ui.table>
                <thead>
                    <tr>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Users</th>
                        <th class="px-4 py-3">Permissions</th>
                        <th class="px-4 py-3 w-14 sr-only">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->roles as $role)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="badge {{ $role->name === 'system-admin' ? 'badge-amber' : 'badge-slate' }} mono">
                                    {{ $role->name }}
                                </span>
                            </td>
                            <td class="px-4 py-3 tabular-nums">{{ $role->users_count }}</td>
                            <td class="px-4 py-3 tabular-nums">
                                @if ($role->name === 'system-admin')
                                    <span class="text-slate-400 dark:text-slate-500 italic text-sm">all (bypasses checks)</span>
                                @else
                                    {{ $role->permissions->count() }}
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.row-actions :label="'Actions for ' . $role->name">
                                    @if ($role->name === 'system-admin')
                                        <x-ui.row-actions.item icon="cog" label="Edit Permissions" :disabled="true" hint="System admin permissions cannot be edited" />
                                        <x-ui.row-actions.item icon="trash" label="Delete Role" :disabled="true" hint="System admin cannot be deleted" />
                                    @else
                                        <x-ui.row-actions.item icon="cog" label="Edit Permissions" wire:click="startEdit('{{ $role->name }}')" />
                                        <x-ui.row-actions.item icon="trash" label="Delete Role" wire:click="confirmDelete('{{ $role->name }}')" class="!text-red-600 dark:!text-red-400" />
                                    @endif
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
        </x-ui.table>
    </div>

    {{-- Delete confirmation modal --}}
    @if ($confirmDeleteRole)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm"
             x-data wire:click.self="cancelDelete">
            <div class="card w-full max-w-sm p-6 space-y-4">
                <div class="flex items-start gap-3">
                    <div class="flex-none w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                        <x-ui.icon name="exclamation" class="w-5 h-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white">Delete Role</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Are you sure you want to delete
                            <span class="font-mono font-semibold text-slate-800 dark:text-slate-200">{{ $confirmDeleteRole }}</span>?
                            This action cannot be undone.
                        </p>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancelDelete" class="btn btn-ghost">Cancel</button>
                    <button type="button" wire:click="deleteRole" wire:loading.attr="disabled"
                            class="btn btn-primary !bg-red-600 hover:!bg-red-700 border-0">
                        <span wire:loading.remove wire:target="deleteRole">
                            <x-ui.icon name="trash" class="w-4 h-4" /> Delete
                        </span>
                        <span wire:loading wire:target="deleteRole">Deleting…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Edit panel --}}
    @if ($editingRole)
        <div class="card overflow-hidden">
            <div class="card-header">
                <h3 class="card-title">
                    Editing permissions for
                    <span class="badge badge-amber mono ml-1">{{ $editingRole }}</span>
                </h3>
                <button type="button" wire:click="cancelEdit" class="btn btn-secondary btn-sm">
                    <x-ui.icon name="x" class="w-4 h-4" /> Cancel
                </button>
            </div>

            <form wire:submit="savePermissions">
                <div class="card-body space-y-6">
                    @foreach ($this->permissionsGrouped as $module => $permissions)
                        <div>
                            <p class="section-label mb-2 capitalize">{{ str_replace(['-', '_'], ' ', $module) }}</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                                @foreach ($permissions as $permission)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            wire:model="selectedPermissions"
                                            value="{{ $permission }}"
                                            class="rounded border-slate-300 dark:border-slate-600
                                                   text-simba-600 focus:ring-simba-600 dark:bg-zinc-800"
                                        >
                                        <span class="text-sm mono text-slate-700 dark:text-slate-300 truncate"
                                              title="{{ $permission }}">
                                            {{ Str::after($permission, '.') }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    @if (empty($this->permissionsGrouped))
                        <p class="text-slate-400 dark:text-slate-500 text-sm">No permissions defined yet.</p>
                    @endif
                </div>

                <div class="card-footer gap-3">
                    <button type="submit" class="btn btn-primary">
                        <x-ui.icon name="check" class="w-4 h-4" /> Save Permissions
                    </button>
                    <button type="button" wire:click="cancelEdit" class="btn btn-secondary">
                        Cancel
                    </button>
                    <p class="text-xs text-slate-400 dark:text-slate-500 ml-auto self-center">
                        {{ count($selectedPermissions) }} permission(s) selected
                    </p>
                </div>
            </form>
        </div>
    @endif
</div>
