<div class="space-y-6">
    {{-- Page header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Roles &amp; Permissions</h2>
            <p class="page-sub">Assign permissions to each role. The system-admin role bypasses all checks via <code class="mono">Gate::before</code>.</p>
        </div>
    </div>

    {{-- Roles table --}}
    <div class="card overflow-hidden">
        <div class="card-header">
            <h3 class="card-title">Roles</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
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
                                    @else
                                        <x-ui.row-actions.item icon="cog" label="Edit Permissions" wire:click="startEdit('{{ $role->name }}')" />
                                    @endif
                                </x-ui.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

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
