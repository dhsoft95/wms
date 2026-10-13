<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">
                Scanner Devices
                @if ($this->unreviewedCount > 0)
                    <span class="ml-2 badge badge-amber align-middle">{{ $this->unreviewedCount }} new</span>
                @endif
            </h2>
            <p class="page-sub">
                Manage scanner devices and their default printers.
                New devices auto-register on first scan.
            </p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <x-ui.table class="min-w-[800px]">

            <thead>
                <tr>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Default Printer</th>
                    <th class="px-4 py-3">Last User</th>
                    <th class="px-4 py-3">Last Seen</th>
                    <th class="px-4 py-3">Active</th>
                    <th class="px-4 py-3 w-14 sr-only">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
            @forelse ($this->devices as $device)
                <tr wire:key="dev-{{ $device->id }}"
                    class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">

                    {{-- Code --}}
                    <td class="px-4 py-3.5">
                        <span class="badge badge-slate mono">{{ $device->code }}</span>
                    </td>

                    {{-- Name (inline-editable) --}}
                    <td class="px-4 py-3.5">
                        <div
                            x-data="{ editing: false, name: '{{ e($device->name) }}' }"
                            class="flex items-center gap-1"
                        >
                            <span
                                x-show="!editing"
                                @click="editing = true; $nextTick(() => $refs.nameInput.select())"
                                class="font-medium text-slate-900 dark:text-slate-100 cursor-text hover:underline decoration-dashed underline-offset-2"
                                title="Click to edit"
                            >
                                {{ $device->name }}
                            </span>
                            <input
                                x-ref="nameInput"
                                x-show="editing"
                                x-model="name"
                                @keydown.enter="editing = false; $wire.updateName({{ $device->id }}, name)"
                                @keydown.escape="editing = false; name = '{{ e($device->name) }}'"
                                @blur="editing = false; $wire.updateName({{ $device->id }}, name)"
                                type="text"
                                class="input text-sm py-1 px-2 w-40"
                            />
                            <button
                                type="button"
                                x-show="!editing"
                                @click="editing = true; $nextTick(() => $refs.nameInput.select())"
                                class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                                title="Edit name"
                            >
                                <x-ui.icon name="pencil" class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </td>

                    {{-- Default Printer (inline select) --}}
                    <td class="px-4 py-3.5">
                        <select
                            x-on:change="$wire.setDefaultPrinter({{ $device->id }}, $event.target.value ? parseInt($event.target.value) : null)"
                            class="input text-sm py-1 px-2 w-44"
                        >
                            <option value="">— none —</option>
                            @foreach ($this->activePrinters as $printer)
                                <option value="{{ $printer->id }}" @selected($device->default_printer_id === $printer->id)>
                                    {{ $printer->name }}
                                </option>
                            @endforeach
                        </select>
                    </td>

                    {{-- Last User --}}
                    <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400 text-sm">
                        {{ $device->lastUser?->name ?? '—' }}
                    </td>

                    {{-- Last Seen --}}
                    <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400 text-sm">
                        @if ($device->last_seen_at)
                            <span title="{{ $device->last_seen_at->toDateTimeString() }}">
                                {{ $device->last_seen_at->diffForHumans() }}
                            </span>
                        @else
                            <span class="text-slate-400">Never</span>
                        @endif
                    </td>

                    {{-- Active --}}
                    <td class="px-4 py-3.5">
                        @if ($device->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-slate">Inactive</span>
                        @endif
                    </td>

                    {{-- Actions --}}
                    <td class="px-4 py-3.5">
                        @if ($device->is_active)
                            <x-ui.row-actions :label="'Actions for ' . $device->name">
                                <x-ui.row-actions.item icon="x" label="Deactivate" tone="warning" wire:confirm="Deactivate {{ $device->name }}?" wire:click="deactivate({{ $device->id }})" />
                            </x-ui.row-actions>
                        @endif
                    </td>

                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-16 text-center">
                        <x-ui.icon name="device-mobile" class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                        <p class="mt-3 font-medium text-slate-900 dark:text-white">No scanner devices registered</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Devices will appear here automatically after their first scan.</p>
                    </td>
                </tr>
            @endforelse
            </tbody>

        </x-ui.table>

        {{-- Pagination --}}
        @if ($this->devices->hasPages())
            <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.08]">
                {{ $this->devices->links() }}
            </div>
        @endif

    </div>

</div>
