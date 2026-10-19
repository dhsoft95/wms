<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Approval Chains</h2>
            <p class="page-sub">Configure per-department approval levels for purchase orders, MRNs, and transfers.</p>
        </div>
        <button type="button" wire:click="openCreateChain" class="btn btn-primary">
            <x-ui.icon name="plus" class="w-4 h-4" /> Add Level
        </button>
    </div>

    {{-- Doc Type Tabs --}}
    <div class="border-b border-white/40 dark:border-white/[0.08]">
        <nav class="-mb-px flex gap-1 overflow-x-auto">
            @php
                $docTabs = [
                    'po'       => 'Purchase Orders',
                    'mrn'      => 'MRN',
                    'transfer' => 'Transfers',
                ];
            @endphp

            @foreach ($docTabs as $key => $label)
                <button
                    wire:click="$set('docType', '{{ $key }}')"
                    class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition-colors
                           {{ $docType === $key
                               ? 'border-simba-500 text-simba-600 dark:text-simba-400'
                               : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:hover:text-slate-300' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Chains Table --}}
    <div class="card overflow-hidden">
        <div class="card-header">
            <h3 class="card-title">
                {{ $docTabs[$docType] ?? 'Chains' }}
                <span class="ml-2 rounded-full bg-slate-100 dark:bg-white/[0.08] px-2 py-0.5 text-xs font-semibold text-slate-500 dark:text-slate-400">
                    {{ $this->chains->count() }}
                </span>
            </h3>
        </div>

        <div wire:loading.class="opacity-50" wire:target="docType">
            <x-ui.table>

                <thead class="text-[11px] font-semibold uppercase tracking-wider
                              text-slate-500 dark:text-slate-400
                              bg-white/[0.45] dark:bg-white/[0.04]
                              border-b border-white/50 dark:border-white/[0.07]">
                    <tr>
                        <th class="px-5 py-3">Department</th>
                        <th class="px-5 py-3 text-center">Level</th>
                        <th class="px-5 py-3">Approvers</th>
                        <th class="px-5 py-3 sr-only">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
                @forelse ($this->chains as $chain)
                    <tr wire:key="chain-{{ $chain->id }}"
                        class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">

                        {{-- Department --}}
                        <td class="px-5 py-4">
                            @if ($chain->department)
                                <span class="font-medium text-slate-800 dark:text-slate-200">
                                    {{ $chain->department->name }}
                                </span>
                                <span class="ml-1.5 rounded bg-slate-100 dark:bg-white/[0.07] px-1.5 py-0.5 text-[11px] font-mono text-slate-500 dark:text-slate-400">
                                    {{ $chain->department->code }}
                                </span>
                            @else
                                <span class="italic text-slate-400 dark:text-slate-500">All departments</span>
                            @endif
                        </td>

                        {{-- Level --}}
                        <td class="px-5 py-4 text-center">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full
                                         bg-simba-100 dark:bg-simba-900/30
                                         text-sm font-bold text-simba-700 dark:text-simba-400">
                                {{ $chain->level }}
                            </span>
                        </td>

                        {{-- Approvers --}}
                        <td class="px-5 py-4">
                            @if ($chain->chainUsers->isEmpty())
                                <span class="text-sm italic text-slate-400 dark:text-slate-500">No approvers assigned</span>
                            @else
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($chain->chainUsers as $cu)
                                        <span class="badge badge-slate text-xs">
                                            {{ $cu->user?->name ?? 'Unknown' }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-5 py-4">
                            <x-ui.row-actions :label="'Actions for level ' . $chain->level">
                                <x-ui.row-actions.item
                                    icon="users"
                                    label="Manage Approvers"
                                    wire:click="openUsersModal({{ $chain->id }})"
                                />
                                <x-ui.row-actions.item
                                    icon="pencil"
                                    label="Edit Level"
                                    wire:click="openEditChain({{ $chain->id }})"
                                />
                                <x-ui.row-actions.divider />
                                <x-ui.row-actions.item
                                    icon="trash"
                                    label="Remove Level"
                                    tone="danger"
                                    wire:confirm="Remove this chain level and all its approvers?"
                                    wire:click="deleteChain({{ $chain->id }})"
                                />
                            </x-ui.row-actions>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-14 text-center">
                            <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 dark:bg-white/[0.06]">
                                <x-ui.icon name="clipboard-list" class="h-5 w-5 text-slate-400" />
                            </div>
                            <p class="font-medium text-slate-900 dark:text-white">No approval levels configured</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Click <strong>Add Level</strong> to define the first approval level for {{ $docTabs[$docType] ?? 'this document type' }}.
                            </p>
                        </td>
                    </tr>
                @endforelse
                </tbody>

            </x-ui.table>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- Modal: Create / Edit Chain Level                                        --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}

    @if ($showChainModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm"
             x-data wire:click.self="closeChainModal">
            <div class="card w-full max-w-md p-6 space-y-5">

                {{-- Header --}}
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white">
                        {{ $editChainId ? 'Edit Chain Level' : 'New Chain Level' }}
                    </h3>
                    <button wire:click="closeChainModal" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>

                {{-- Department --}}
                <div>
                    <label for="chainDeptId" class="label">Department</label>
                    <select id="chainDeptId" wire:model="chainDeptId" class="input w-full">
                        <option value="">All Departments (global fallback)</option>
                        @foreach ($this->departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }} ({{ $dept->code }})</option>
                        @endforeach
                    </select>
                    <p class="hint">Leave blank to apply this level to all departments globally.</p>
                    @error('chainDeptId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Document Type --}}
                <div>
                    <label for="chainDocType" class="label">Document Type <span class="text-red-500">*</span></label>
                    <select id="chainDocType" wire:model="chainDocType" class="input w-full">
                        <option value="po">Purchase Order (PO)</option>
                        <option value="mrn">Material Receipt Note (MRN)</option>
                        <option value="transfer">Stock Transfer</option>
                    </select>
                    @error('chainDocType') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Level --}}
                <div>
                    <label for="chainLevel" class="label">Approval Level <span class="text-red-500">*</span></label>
                    <input
                        id="chainLevel"
                        wire:model="chainLevel"
                        type="number"
                        min="1"
                        max="10"
                        class="input w-full sm:max-w-xs"
                        placeholder="1"
                    />
                    <p class="hint">Level 1 is the first approver, level 2 is next, and so on. Max 10.</p>
                    @error('chainLevel') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Footer --}}
                <div class="flex justify-end gap-2 border-t border-white/30 dark:border-white/[0.06] pt-4">
                    <button type="button" wire:click="closeChainModal" class="btn btn-ghost">Cancel</button>
                    <button
                        type="button"
                        wire:click="saveChain"
                        wire:loading.attr="disabled"
                        wire:target="saveChain"
                        class="btn btn-primary"
                    >
                        <span wire:loading.remove wire:target="saveChain">
                            <x-ui.icon name="check" class="w-4 h-4" />
                            {{ $editChainId ? 'Update Level' : 'Create Level' }}
                        </span>
                        <span wire:loading wire:target="saveChain">Saving…</span>
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- Modal: Manage Approvers                                                 --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}

    @if ($showUsersModal)
        @php
            $managedChain = $this->chains->firstWhere('id', $manageChainId);
        @endphp

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm"
             x-data wire:click.self="closeUsersModal">
            <div class="card w-full max-w-lg p-6 space-y-5">

                {{-- Header --}}
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white">Manage Approvers</h3>
                        @if ($managedChain)
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                {{ $managedChain->department?->name ?? 'All Departments' }}
                                &mdash; Level {{ $managedChain->level }}
                                &mdash; {{ strtoupper($managedChain->document_type instanceof \BackedEnum ? $managedChain->document_type->value : $managedChain->document_type) }}
                            </p>
                        @endif
                    </div>
                    <button wire:click="closeUsersModal" class="icon-btn">
                        <x-ui.icon name="x" class="w-5 h-5" />
                    </button>
                </div>

                {{-- Existing approvers --}}
                <div>
                    <p class="label mb-2">Current Approvers</p>

                    @if ($managedChain && $managedChain->chainUsers->isNotEmpty())
                        <ul class="divide-y divide-white/40 dark:divide-white/[0.07] rounded-xl border border-white/40 dark:border-white/[0.08]">
                            @foreach ($managedChain->chainUsers as $cu)
                                <li wire:key="cu-{{ $cu->id }}"
                                    class="flex items-center justify-between gap-3 px-4 py-2.5
                                           first:rounded-t-xl last:rounded-b-xl
                                           transition hover:bg-white/30 dark:hover:bg-white/[0.04]">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-slate-800 dark:text-slate-200 text-sm">
                                            {{ $cu->user?->name ?? 'Unknown User' }}
                                        </p>
                                        @if ($cu->user)
                                            <p class="truncate text-xs text-slate-400 dark:text-slate-500">{{ $cu->user->email }}</p>
                                        @endif
                                    </div>
                                    <button
                                        wire:click="removeUser({{ $cu->id }})"
                                        wire:loading.attr="disabled"
                                        wire:confirm="Remove {{ $cu->user?->name }} from this level?"
                                        class="btn btn-ghost text-xs text-red-600 hover:text-red-700
                                               dark:text-red-400 dark:hover:text-red-300 shrink-0"
                                    >
                                        <x-ui.icon name="trash" class="w-3.5 h-3.5" />
                                        Remove
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="rounded-xl border border-dashed border-white/40 dark:border-white/[0.08] px-4 py-6 text-center">
                            <p class="text-sm text-slate-400 dark:text-slate-500">No approvers assigned to this level yet.</p>
                        </div>
                    @endif
                </div>

                {{-- Add User --}}
                <div class="space-y-2 border-t border-white/30 dark:border-white/[0.06] pt-4">
                    <p class="label">Add Approver</p>
                    <div class="flex gap-2">
                        <select wire:model="addUserId" class="input flex-1">
                            <option value="">Select a user…</option>
                            @foreach ($this->availableUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>
                            @endforeach
                        </select>
                        <button
                            type="button"
                            wire:click="addUser"
                            wire:loading.attr="disabled"
                            wire:target="addUser"
                            class="btn btn-primary shrink-0"
                        >
                            <span wire:loading.remove wire:target="addUser">
                                <x-ui.icon name="plus" class="w-4 h-4" /> Add
                            </span>
                            <span wire:loading wire:target="addUser">Adding…</span>
                        </button>
                    </div>
                    @error('addUserId') <p class="hint !text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Footer --}}
                <div class="flex justify-end border-t border-white/30 dark:border-white/[0.06] pt-4">
                    <button type="button" wire:click="closeUsersModal" class="btn btn-secondary">
                        Done
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>
