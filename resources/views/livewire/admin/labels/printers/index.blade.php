<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">
                Printers
                <span class="ml-2 align-middle text-sm font-medium text-slate-400 dark:text-slate-500">{{ $this->printers->count() }}</span>
            </h2>
            <p class="page-sub">Manage network and virtual label printers.</p>
        </div>
        @can('printers.manage')
            <a href="{{ route('admin.printers.create') }}" wire:navigate class="btn btn-primary">
                <x-ui.icon name="plus" class="w-4 h-4" /> Add Printer
            </a>
        @endcan
    </div>

    <div class="card overflow-hidden">
        <x-ui.table class="min-w-[640px]">

            <thead>
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Kind</th>
                    <th class="px-4 py-3">IP : Port</th>
                    <th class="px-4 py-3">DPI</th>
                    <th class="px-4 py-3">Location</th>
                    <th class="px-4 py-3">Active</th>
                    <th class="px-4 py-3 w-14 sr-only">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
            @forelse ($this->printers as $printer)
                <tr wire:key="pr-{{ $printer->id }}"
                    class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">

                    {{-- Name --}}
                    <td class="px-4 py-3.5 font-medium text-slate-900 dark:text-slate-100">
                        {{ $printer->name }}
                    </td>

                    {{-- Kind --}}
                    <td class="px-4 py-3.5">
                        <span class="badge {{ $printer->kind->color() }}">{{ $printer->kind->label() }}</span>
                    </td>

                    {{-- IP:Port --}}
                    <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400 mono text-sm">
                        @if ($printer->ip)
                            {{ $printer->ip }}:{{ $printer->port }}
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>

                    {{-- DPI --}}
                    <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400">
                        {{ $printer->dpi }}
                    </td>

                    {{-- Location Note --}}
                    <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400 max-w-[160px] truncate">
                        {{ $printer->location_note ?? '—' }}
                    </td>

                    {{-- Active --}}
                    <td class="px-4 py-3.5">
                        @if ($printer->is_active)
                            <span class="badge badge-green">Active</span>
                        @else
                            <span class="badge badge-slate">Inactive</span>
                        @endif
                    </td>

                    {{-- Actions --}}
                    <td class="px-4 py-3.5">
                        <x-ui.row-actions :label="'Actions for ' . $printer->name">
                            <x-ui.row-actions.item icon="pencil" label="Edit" :href="route('admin.printers.edit', $printer)" wire:navigate />
                            @if ($printer->is_active)
                                <x-ui.row-actions.divider />
                                <x-ui.row-actions.item icon="x" label="Deactivate" tone="warning" wire:confirm="Deactivate {{ $printer->name }}?" wire:click="deactivate({{ $printer->id }})" />
                            @endif
                        </x-ui.row-actions>
                    </td>

                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-16 text-center">
                        <x-ui.icon name="printer" class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                        <p class="mt-3 font-medium text-slate-900 dark:text-white">No printers configured</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Add your first printer to enable label printing.</p>
                    </td>
                </tr>
            @endforelse
            </tbody>

        </x-ui.table>
    </div>

</div>
