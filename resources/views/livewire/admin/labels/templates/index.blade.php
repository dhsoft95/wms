<div x-data="{ compact: $persist(false).as('table-density') }" class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Label Templates</h2>
            <p class="page-sub">Manage ZPL and PDF label templates for printing.</p>
        </div>
        @can('labels.templates.manage')
            <a href="{{ route('admin.labels.templates.create') }}" wire:navigate class="btn btn-primary">
                <x-ui.icon name="plus" class="w-4 h-4" /> New Template
            </a>
        @endcan
    </div>

    {{-- Filters --}}
    <div class="card overflow-hidden" :class="compact ? 'density-compact' : ''">

        <div class="flex flex-col gap-3 p-4 lg:flex-row border-b border-white/40 dark:border-white/[0.08]">
            {{-- Search --}}
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    wire:model.live="search"
                    type="search"
                    placeholder="Search by name…"
                    class="input w-full pl-9"
                />
            </div>

            {{-- Type filter --}}
            <div class="lg:w-48">
                <select wire:model.live="filterType" class="input w-full">
                    <option value="">All Types</option>
                    @foreach (\App\Enums\LabelTemplateType::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Active filter --}}
            <div class="lg:w-36">
                <select wire:model.live="filterActive" class="input w-full">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                    <option value="">All</option>
                </select>
            </div>

            <div class="flex items-center gap-1 tabs shrink-0">
                <button type="button" @click="compact = false" :class="!compact ? 'tab-active' : ''" class="tab text-xs">Comfortable</button>
                <button type="button" @click="compact = true"  :class="compact  ? 'tab-active' : ''" class="tab text-xs">Compact</button>
            </div>
        </div>

        {{-- Table --}}
        <div wire:loading.class="opacity-50">
            <x-ui.table class="min-w-[800px]">

                <thead>
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Size (mm)</th>
                        <th class="px-4 py-3">DPI</th>
                        <th class="px-4 py-3">Default</th>
                        <th class="px-4 py-3">Active</th>
                        <th class="px-4 py-3 w-14 sr-only">Actions</th>
                    </tr>
                </thead>

                <tbody wire:loading.remove class="divide-y divide-white/50 dark:divide-white/[0.06]">
                @forelse ($this->templates as $template)
                    <tr wire:key="tpl-{{ $template->id }}"
                        class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">

                        {{-- Name --}}
                        <td class="px-4 py-3.5 font-medium text-slate-900 dark:text-slate-100">
                            {{ $template->name }}
                        </td>

                        {{-- Type --}}
                        <td class="px-4 py-3.5">
                            <span class="badge {{ $template->type->color() }}">{{ $template->type->label() }}</span>
                        </td>

                        {{-- Size --}}
                        <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400 mono text-sm">
                            {{ $template->width_mm }} × {{ $template->height_mm }}
                        </td>

                        {{-- DPI --}}
                        <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400">
                            {{ $template->dpi }}
                        </td>

                        {{-- Default --}}
                        <td class="px-4 py-3.5">
                            @if ($template->is_default)
                                <span class="badge badge-brand">Default</span>
                            @else
                                <span class="text-slate-400 dark:text-slate-600 text-sm">—</span>
                            @endif
                        </td>

                        {{-- Active --}}
                        <td class="px-4 py-3.5">
                            @if ($template->is_active)
                                <span class="badge badge-green">Active</span>
                            @else
                                <span class="badge badge-slate">Inactive</span>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3.5">
                            <x-ui.row-actions :label="'Actions for ' . $template->name">
                                <x-ui.row-actions.item icon="pencil" label="Edit" :href="route('admin.labels.templates.edit', $template)" wire:navigate />
                                <x-ui.row-actions.divider />
                                @unless ($template->is_default)
                                    <x-ui.row-actions.item icon="star" label="Set Default" wire:click="setDefault({{ $template->id }})" />
                                @endunless
                                <x-ui.row-actions.item icon="duplicate" label="Duplicate" wire:click="duplicate({{ $template->id }})" />
                                @if ($template->is_active)
                                    <x-ui.row-actions.item icon="x" label="Deactivate" tone="warning" wire:confirm="Deactivate {{ $template->name }}?" wire:click="deactivate({{ $template->id }})" />
                                @endif
                            </x-ui.row-actions>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-16 text-center">
                            <x-ui.icon name="tag" class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                            <p class="mt-3 font-medium text-slate-900 dark:text-white">No label templates found</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ $search || $filterType ? 'Try adjusting your filters.' : 'Create your first label template to get started.' }}
                            </p>
                        </td>
                    </tr>
                @endforelse
                </tbody>

                <tbody wire:loading class="divide-y divide-white/50 dark:divide-white/[0.06]">
                    @for ($i = 0; $i < 5; $i++)
                        <tr class="skeleton-row">
                            <td class="px-4"><div class="skeleton-cell w-48"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-24"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-20"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-12"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-16"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-14"></div></td>
                            <td class="px-4 w-14"><div class="skeleton-cell w-8 ml-auto"></div></td>
                        </tr>
                    @endfor
                </tbody>

            </x-ui.table>
        </div>

        {{-- Pagination --}}
        @if ($this->templates->hasPages())
            <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.08]">
                {{ $this->templates->links() }}
            </div>
        @endif

    </div>

</div>
