<div class="space-y-6">
    {{-- Page header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Departments</h2>
            <p class="page-sub">Manage organisational departments and assign users.</p>
        </div>
    </div>

    {{-- Create / Edit form --}}
    <div class="card max-w-lg">
        <div class="card-header">
            <h3 class="card-title">{{ $editingId ? 'Edit Department' : 'New Department' }}</h3>
            @if ($editingId)
                <button type="button" wire:click="cancelEdit" class="btn btn-ghost btn-sm">
                    <x-ui.icon name="x" class="w-4 h-4" /> Cancel
                </button>
            @endif
        </div>
        <form wire:submit="save">
            <div class="card-body space-y-4">
                <div>
                    <label class="label">Name *</label>
                    <input wire:model="name" type="text" required placeholder="e.g. Warehouse Operations" class="input">
                    @error('name') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Code *</label>
                    <input wire:model="code" type="text" required placeholder="e.g. WH-OPS" class="input mono uppercase">
                    <p class="hint">Short unique identifier. Will be uppercased automatically.</p>
                    @error('code') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <x-ui.icon name="{{ $editingId ? 'check' : 'plus' }}" class="w-4 h-4" />
                    {{ $editingId ? 'Update Department' : 'Create Department' }}
                </button>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="card">
        <div class="card-header">
            <input wire:model.live="search" type="search" placeholder="Search departments…" class="input w-64">
            <span class="badge badge-slate">{{ $this->departments->count() }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Users</th>
                        <th>Active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->departments as $dept)
                        <tr class="{{ $dept->trashed() ? 'opacity-50' : '' }}">
                            <td class="{{ $dept->trashed() ? 'line-through text-slate-400' : 'font-medium' }}">
                                {{ $dept->name }}
                            </td>
                            <td>
                                <span class="badge badge-slate mono">{{ $dept->code }}</span>
                            </td>
                            <td class="tabular-nums">{{ $dept->users_count }}</td>
                            <td>
                                @if ($dept->trashed())
                                    <span class="badge badge-slate">Deleted</span>
                                @else
                                    <button
                                        type="button"
                                        wire:click="toggleActive({{ $dept->id }})"
                                        class="btn btn-ghost btn-sm"
                                        title="{{ $dept->is_active ? 'Deactivate' : 'Activate' }}"
                                    >
                                        @if ($dept->is_active)
                                            <span class="badge badge-green">Active</span>
                                        @else
                                            <span class="badge badge-amber">Inactive</span>
                                        @endif
                                    </button>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($dept->trashed())
                                    <button type="button" wire:click="restore({{ $dept->id }})"
                                        class="btn btn-ghost btn-sm text-simba-600">
                                        <x-ui.icon name="refresh" class="w-4 h-4" /> Restore
                                    </button>
                                @else
                                    <button type="button" wire:click="startEdit({{ $dept->id }})"
                                        class="btn btn-ghost btn-sm">
                                        <x-ui.icon name="cog" class="w-4 h-4" /> Edit
                                    </button>

                                    @if ($confirmingDelete === $dept->id)
                                        <span class="text-sm text-slate-500 mr-1">Sure?</span>
                                        <button type="button" wire:click="deleteOrDeactivate({{ $dept->id }})"
                                            class="btn btn-danger btn-sm">
                                            <x-ui.icon name="trash" class="w-4 h-4" /> Yes, delete
                                        </button>
                                        <button type="button" wire:click="$set('confirmingDelete', null)"
                                            class="btn btn-ghost btn-sm">
                                            Cancel
                                        </button>
                                    @else
                                        <button type="button"
                                            wire:click="$set('confirmingDelete', {{ $dept->id }})"
                                            class="btn btn-ghost btn-sm text-red-600"
                                            title="{{ $dept->users_count > 0 ? 'Has users — will deactivate instead of deleting' : 'Delete department' }}">
                                            <x-ui.icon name="trash" class="w-4 h-4" />
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-slate-400 py-8">
                                No departments found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
