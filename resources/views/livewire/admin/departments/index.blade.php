<div class="space-y-6">

    {{-- Page header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">
                Departments
                <span class="ml-2 align-middle text-sm font-medium text-slate-400 dark:text-slate-500">{{ $this->departments->count() }}</span>
            </h2>
            <p class="page-sub">Manage organisational departments and their virtual warehouses.</p>
        </div>
        @can('departments.manage')
            <button type="button" wire:click="openCreateDept" class="btn btn-primary">
                <x-ui.icon name="plus" class="w-4 h-4" />
                New Department
            </button>
        @endcan
    </div>

    {{-- Departments table card --}}
    <div class="card overflow-hidden">

        {{-- Toolbar --}}
        <div class="flex flex-col gap-3 p-4 lg:flex-row lg:items-center border-b border-white/40 dark:border-white/[0.08]">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="Search departments…"
                    class="input w-full pl-9"
                />
            </div>
            <div class="flex items-center gap-1 tabs shrink-0">
                <button type="button" wire:click="$set('filterStatus','all')"
                        @class(['tab', 'tab-active' => $filterStatus === 'all'])>All</button>
                <button type="button" wire:click="$set('filterStatus','active')"
                        @class(['tab', 'tab-active' => $filterStatus === 'active'])>Active</button>
                <button type="button" wire:click="$set('filterStatus','inactive')"
                        @class(['tab', 'tab-active' => $filterStatus === 'inactive'])>Inactive</button>
            </div>
        </div>

        {{-- Table --}}
        <div wire:loading.class="opacity-50">
            <x-ui.table class="min-w-[960px]">

                <thead class="text-[11px] font-semibold uppercase tracking-wider
                              text-slate-500 dark:text-slate-400
                              bg-white/[0.45] dark:bg-white/[0.04]
                              border-b border-white/50 dark:border-white/[0.07]">
                <tr>
                    <th class="w-8 px-3 py-3"></th>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Head of Department</th>
                    <th class="px-4 py-3">Report Email</th>
                    <th class="px-4 py-3 text-center">VWs</th>
                    <th class="px-4 py-3 text-center">Users</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
                @forelse ($this->departments as $dept)

                    {{-- Dept row --}}
                    <tr wire:key="dept-{{ $dept->id }}"
                        @class(['transition',
                                'opacity-60'                                         => $dept->trashed(),
                                'hover:bg-white/30 dark:hover:bg-white/[0.04]'       => !$dept->trashed()])>

                        {{-- Expand chevron --}}
                        <td class="px-3 py-3.5">
                            @if ($dept->virtual_warehouses_count > 0)
                                <button type="button"
                                        wire:click="toggleExpand({{ $dept->id }})"
                                        class="icon-btn text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">
                                    <x-ui.icon name="chevron-right"
                                               @class(['h-4 w-4 transition-transform duration-150',
                                                       'rotate-90' => in_array($dept->id, $expandedDepts, true)]) />
                                </button>
                            @endif
                        </td>

                        {{-- Code --}}
                        <td class="px-4 py-3.5">
                            <span class="badge badge-slate mono">{{ $dept->code }}</span>
                        </td>

                        {{-- Name --}}
                        <td class="px-4 py-3.5 font-medium
                                   {{ $dept->trashed() ? 'line-through text-slate-400 dark:text-slate-600' : 'text-slate-900 dark:text-slate-100' }}">
                            {{ $dept->name }}
                        </td>

                        {{-- Head of Department --}}
                        <td class="px-4 py-3.5 text-slate-600 dark:text-slate-300">
                            {{ $dept->head?->name ?? '—' }}
                        </td>

                        {{-- Report Email --}}
                        <td class="px-4 py-3.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ $dept->report_email ?? '—' }}
                        </td>

                        {{-- VWs count --}}
                        <td class="px-4 py-3.5 text-center tabular-nums text-slate-700 dark:text-slate-300">
                            {{ $dept->virtual_warehouses_count }}
                        </td>

                        {{-- Users count --}}
                        <td class="px-4 py-3.5 text-center tabular-nums text-slate-700 dark:text-slate-300">
                            {{ $dept->users_count }}
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3.5">
                            @if ($dept->trashed())
                                <span class="badge badge-slate">Deleted</span>
                            @elseif ($dept->is_active)
                                <span class="badge badge-green">Active</span>
                            @else
                                <span class="badge badge-amber">Inactive</span>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3.5">
                            <x-ui.row-actions :label="'Actions for ' . $dept->name">
                                @if ($dept->trashed())
                                    <x-ui.row-actions.item icon="refresh" label="Restore" tone="info" wire:click="restore({{ $dept->id }}, 'dept')" />
                                @else
                                    @can('virtual-warehouses.manage')
                                        <x-ui.row-actions.item icon="home" label="Add VW" wire:click="openCreateVw({{ $dept->id }})" />
                                    @endcan
                                    <x-ui.row-actions.item icon="pencil" label="Edit" wire:click="openEditDept({{ $dept->id }})" />
                                    <x-ui.row-actions.divider />
                                    @unless ($dept->is_active)
                                        <x-ui.row-actions.item icon="check" label="Activate" tone="success" wire:click="openConfirmActivate('dept', {{ $dept->id }})" />
                                    @endunless
                                    <x-ui.row-actions.item icon="trash" label="Delete / Deactivate" tone="danger" wire:click="openConfirmDeleteOrDeactivate('dept', {{ $dept->id }})" />
                                @endif
                            </x-ui.row-actions>
                        </td>

                    </tr>

                    {{-- VW sub-table rows (expanded) --}}
                    @if (in_array($dept->id, $expandedDepts, true))
                        @foreach ($dept->virtualWarehouses as $vw)
                            <tr wire:key="vw-{{ $vw->id }}"
                                class="bg-slate-50/70 dark:bg-white/[0.02] transition">

                                {{-- Indent indicator --}}
                                <td class="px-3 py-2.5 text-right">
                                    <x-ui.icon name="chevron-right" class="ml-auto h-3 w-3 text-slate-300 dark:text-slate-600" />
                                </td>

                                {{-- Code --}}
                                <td class="px-4 py-2.5">
                                    <span class="badge badge-slate mono text-[11px]">{{ $vw->code }}</span>
                                </td>

                                {{-- Name --}}
                                <td class="px-4 py-2.5 text-sm
                                           {{ $vw->trashed() ? 'line-through text-slate-400 dark:text-slate-600' : 'text-slate-700 dark:text-slate-300' }}">
                                    {{ $vw->name }}
                                </td>

                                {{-- Head / Email columns — empty for VW rows --}}
                                <td class="px-4 py-2.5" colspan="2">
                                    <span class="text-[11px] text-slate-400 dark:text-slate-500">Virtual Warehouse</span>
                                </td>

                                {{-- VWs placeholder --}}
                                <td class="px-4 py-2.5 text-center text-xs text-slate-400">—</td>

                                {{-- Users count --}}
                                <td class="px-4 py-2.5 text-center tabular-nums text-xs text-slate-600 dark:text-slate-400">
                                    {{ $vw->users_count }}
                                </td>

                                {{-- Status --}}
                                <td class="px-4 py-2.5">
                                    @if ($vw->trashed())
                                        <span class="badge badge-slate text-[11px]">Deleted</span>
                                    @elseif ($vw->is_active)
                                        <span class="badge badge-green text-[11px]">Active</span>
                                    @else
                                        <span class="badge badge-amber text-[11px]">Inactive</span>
                                    @endif
                                </td>

                                {{-- VW Actions --}}
                                <td class="px-4 py-2.5">
                                    <x-ui.row-actions :label="'Actions for ' . $vw->name">
                                        @if ($vw->trashed())
                                            <x-ui.row-actions.item icon="refresh" label="Restore" tone="info" wire:click="restore({{ $vw->id }}, 'vw')" />
                                        @else
                                            @can('virtual-warehouses.manage')
                                                <x-ui.row-actions.item icon="pencil" label="Edit" wire:click="openEditVw({{ $vw->id }})" />
                                                <x-ui.row-actions.divider />
                                                @unless ($vw->is_active)
                                                    <x-ui.row-actions.item icon="check" label="Activate" tone="success" wire:click="openConfirmActivate('vw', {{ $vw->id }})" />
                                                @endunless
                                                <x-ui.row-actions.item icon="trash" label="Delete / Deactivate" tone="danger" wire:click="openConfirmDeleteOrDeactivate('vw', {{ $vw->id }})" />
                                            @endcan
                                        @endif
                                    </x-ui.row-actions>
                                </td>

                            </tr>
                        @endforeach
                    @endif

                @empty
                    <tr>
                        <td colspan="9" class="px-5 py-16 text-center">
                            <x-ui.icon name="building" class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                            <p class="mt-3 font-medium text-slate-900 dark:text-white">No departments found</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Try adjusting your search or create a new department.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>

            </x-ui.table>
        </div>

    </div>

    {{-- ─── Department Create / Edit Modal ──────────────────────────────────── --}}

    @if ($showDeptModal)
        <x-ui.modal
            :title="$editingDeptId ? 'Edit Department' : 'New Department'"
            wireClose="closeDeptModal"
            maxWidth="lg"
        >
            <div>
                <label class="label">Name <span class="required-mark">*</span></label>
                <input wire:model="deptForm.name" type="text"
                       placeholder="e.g. Warehouse Operations" class="input w-full">
                @error('deptForm.name')
                    <p class="hint hint-error mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label">Code <span class="required-mark">*</span></label>
                <input wire:model="deptForm.code" type="text"
                       placeholder="e.g. WH-OPS" class="input w-full mono uppercase">
                <p class="hint">Short unique identifier. Uppercased automatically.</p>
                @error('deptForm.code')
                    <p class="hint hint-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label">Head of Department</label>
                <select wire:model="deptForm.headUserId" class="input w-full">
                    <option value="">— None —</option>
                    @foreach ($this->users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                @error('deptForm.headUserId')
                    <p class="hint hint-error mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label">Report Email</label>
                <input wire:model="deptForm.reportEmail" type="email"
                       placeholder="reports@example.com" class="input w-full">
                @error('deptForm.reportEmail')
                    <p class="hint hint-error mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3">
                <input wire:model="deptForm.isActive" type="checkbox"
                       id="dept-is-active"
                       class="h-4 w-4 rounded border-slate-300 text-simba-600 focus:ring-simba-500">
                <label for="dept-is-active" class="text-sm text-slate-700 dark:text-slate-300">Active</label>
            </div>

            <x-slot:footer>
                <button type="button" wire:click="closeDeptModal" class="btn btn-secondary">Cancel</button>
                <button type="button" wire:click="saveDept" class="btn btn-primary">
                    <x-ui.icon name="{{ $editingDeptId ? 'check' : 'plus' }}" class="w-4 h-4" />
                    {{ $editingDeptId ? 'Update Department' : 'Create Department' }}
                </button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- ─── Virtual Warehouse Create / Edit Modal ───────────────────────────── --}}

    @if ($showVwModal)
        <x-ui.modal
            :title="$editingVwId ? 'Edit Virtual Warehouse' : 'New Virtual Warehouse'"
            wireClose="closeVwModal"
            maxWidth="lg"
        >
            <div>
                <label class="label">Name <span class="required-mark">*</span></label>
                <input wire:model="vwForm.name" type="text"
                       placeholder="e.g. General Store" class="input w-full">
                @error('vwForm.name')
                    <p class="hint hint-error mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="label">Code <span class="required-mark">*</span></label>
                <input wire:model="vwForm.code" type="text"
                       placeholder="e.g. GS" class="input w-full mono uppercase">
                <p class="hint">Short unique identifier. Uppercased automatically.</p>
                @error('vwForm.code')
                    <p class="hint hint-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3">
                <input wire:model="vwForm.isActive" type="checkbox"
                       id="vw-is-active"
                       class="h-4 w-4 rounded border-slate-300 text-simba-600 focus:ring-simba-500">
                <label for="vw-is-active" class="text-sm text-slate-700 dark:text-slate-300">Active</label>
            </div>

            <x-slot:footer>
                <button type="button" wire:click="closeVwModal" class="btn btn-secondary">Cancel</button>
                <button type="button" wire:click="saveVw" class="btn btn-primary">
                    <x-ui.icon name="{{ $editingVwId ? 'check' : 'plus' }}" class="w-4 h-4" />
                    {{ $editingVwId ? 'Update VW' : 'Create VW' }}
                </button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- ─── Confirm Delete / Deactivate / Activate Modal ───────────────────── --}}

    @if ($showConfirmModal)
        @php
            $isActivate  = $confirmAction === 'activate';
            $btnLabel    = $isActivate ? 'Activate' : ($confirmInUse ? 'Deactivate' : 'Delete');
            $btnClass    = $isActivate ? 'btn btn-primary' : ($confirmInUse ? 'btn btn-secondary' : 'btn btn-danger');
            $modelLabel  = $confirmType === 'dept' ? 'department' : 'virtual warehouse';
            $modalTitle  = ($isActivate ? 'Activate' : ($confirmInUse ? 'Deactivate' : 'Delete')) . ' ' . ucwords($modelLabel);
        @endphp
        <x-ui.modal :title="$modalTitle" wireClose="closeConfirmModal" maxWidth="md">

            @if ($isActivate)
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Activate <strong class="text-slate-900 dark:text-white">{{ $confirmName }}</strong>?
                    Users will be able to access it again.
                </p>
            @elseif ($confirmInUse)
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    <strong class="text-slate-900 dark:text-white">{{ $confirmName }}</strong>
                    cannot be deleted because it is currently in use:
                </p>
                <ul class="mt-3 space-y-1.5 text-sm">
                    @if ($confirmUsersCount > 0)
                        <li class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                            <x-ui.icon name="user" class="h-4 w-4 shrink-0 text-amber-500" />
                            {{ $confirmUsersCount }} assigned {{ $confirmUsersCount === 1 ? 'user' : 'users' }}
                        </li>
                    @endif
                    @if ($confirmVwsCount > 0)
                        <li class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                            <x-ui.icon name="home" class="h-4 w-4 shrink-0 text-amber-500" />
                            {{ $confirmVwsCount }} virtual {{ $confirmVwsCount === 1 ? 'warehouse' : 'warehouses' }}
                        </li>
                    @endif
                    @if ($confirmItemsCount > 0)
                        <li class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                            <x-ui.icon name="box" class="h-4 w-4 shrink-0 text-amber-500" />
                            {{ $confirmItemsCount }} assigned {{ $confirmItemsCount === 1 ? 'item' : 'items' }}
                        </li>
                    @endif
                </ul>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                    It will be <strong>deactivated</strong> instead. You can delete it after removing these associations.
                </p>
            @else
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Permanently delete
                    <strong class="text-slate-900 dark:text-white">{{ $confirmName }}</strong>?
                    This action cannot be undone.
                </p>
            @endif

            <x-slot:footer>
                <button type="button" wire:click="closeConfirmModal" class="btn btn-secondary">Cancel</button>
                <button type="button" wire:click="executeConfirm" class="{{ $btnClass }}">{{ $btnLabel }}</button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

</div>
