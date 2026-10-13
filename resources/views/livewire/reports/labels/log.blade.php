<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Label Print Log</h2>
            <p class="page-sub">Audit trail of all label print jobs.</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card overflow-hidden">

        <div class="flex flex-col gap-3 p-4 lg:flex-row border-b border-white/40 dark:border-white/[0.08]">

            {{-- Type filter --}}
            <div class="lg:w-44">
                <select wire:model.live="filterType" class="input w-full">
                    <option value="">All Types</option>
                    @foreach ($templateTypes as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>

            {{-- User search --}}
            <div class="relative flex-1">
                <x-ui.icon name="user" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    wire:model.live="filterUser"
                    type="search"
                    placeholder="Filter by user…"
                    class="input w-full pl-9"
                />
            </div>

            {{-- Date From --}}
            <div class="lg:w-40">
                <input
                    wire:model.live="dateFrom"
                    type="date"
                    class="input w-full"
                    placeholder="From"
                />
            </div>

            {{-- Date To --}}
            <div class="lg:w-40">
                <input
                    wire:model.live="dateTo"
                    type="date"
                    class="input w-full"
                    placeholder="To"
                />
            </div>

            {{-- Reprint only --}}
            <div class="flex items-center gap-2 shrink-0">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        wire:model.live="filterReprint"
                        type="checkbox"
                        value="1"
                        class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800"
                    />
                    <span class="text-sm text-slate-700 dark:text-slate-300 whitespace-nowrap">Reprints only</span>
                </label>
            </div>

        </div>

        {{-- Table --}}
        <div wire:loading.class="opacity-50">
            <x-ui.table class="min-w-[900px]">

                <thead>
                    <tr>
                        <th class="px-4 py-3">Date / Time</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Printable</th>
                        <th class="px-4 py-3">Template</th>
                        <th class="px-4 py-3 text-center">Copies</th>
                        <th class="px-4 py-3">Printer</th>
                        <th class="px-4 py-3">Output</th>
                        <th class="px-4 py-3">Printed By</th>
                    </tr>
                </thead>

                <tbody wire:loading.remove class="divide-y divide-white/50 dark:divide-white/[0.06]">
                @forelse ($this->jobs as $job)
                    <tr wire:key="job-{{ $job->id }}"
                        class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">

                        {{-- Date/Time --}}
                        <td class="px-4 py-3.5 text-sm text-slate-600 dark:text-slate-400 whitespace-nowrap">
                            <span title="{{ $job->created_at->toDateTimeString() }}">
                                {{ $job->created_at->format('d M Y H:i') }}
                            </span>
                        </td>

                        {{-- Type --}}
                        <td class="px-4 py-3.5">
                            @if ($job->labelTemplate)
                                <span class="badge {{ $job->labelTemplate->type->color() }}">{{ $job->labelTemplate->type->label() }}</span>
                            @else
                                <span class="text-slate-400 text-sm">—</span>
                            @endif
                        </td>

                        {{-- Printable --}}
                        <td class="px-4 py-3.5 text-sm text-slate-600 dark:text-slate-400">
                            @if ($job->printable_type)
                                @php
                                    $modelParts = explode('\\', $job->printable_type);
                                    $modelName  = end($modelParts);
                                @endphp
                                <span class="mono">{{ $modelName }} #{{ $job->printable_id }}</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>

                        {{-- Template --}}
                        <td class="px-4 py-3.5 text-sm text-slate-700 dark:text-slate-300">
                            {{ $job->labelTemplate?->name ?? '—' }}
                        </td>

                        {{-- Copies --}}
                        <td class="px-4 py-3.5 text-center text-sm text-slate-600 dark:text-slate-400">
                            {{ $job->copies }}
                        </td>

                        {{-- Printer --}}
                        <td class="px-4 py-3.5 text-sm text-slate-600 dark:text-slate-400">
                            {{ $job->printer?->name ?? '—' }}
                        </td>

                        {{-- Output + Reprint --}}
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                @if ($job->output)
                                    <span class="badge badge-slate">{{ strtoupper($job->output->value) }}</span>
                                @endif
                                @if ($job->is_reprint)
                                    <span class="badge badge-red">Reprint</span>
                                @endif
                            </div>
                        </td>

                        {{-- Printed By --}}
                        <td class="px-4 py-3.5 text-sm text-slate-600 dark:text-slate-400">
                            {{ $job->printedBy?->name ?? '—' }}
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-16 text-center">
                            <x-ui.icon name="printer" class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                            <p class="mt-3 font-medium text-slate-900 dark:text-white">No print jobs found</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ $filterType || $filterUser || $dateFrom || $dateTo || $filterReprint ? 'Try adjusting your filters.' : 'No labels have been printed yet.' }}
                            </p>
                        </td>
                    </tr>
                @endforelse
                </tbody>

                <tbody wire:loading class="divide-y divide-white/50 dark:divide-white/[0.06]">
                    @for ($i = 0; $i < 5; $i++)
                        <tr class="skeleton-row">
                            <td class="px-4"><div class="skeleton-cell w-32"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-20"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-24"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-36"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-8 mx-auto"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-28"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-16"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-28"></div></td>
                        </tr>
                    @endfor
                </tbody>

            </x-ui.table>
        </div>

        {{-- Pagination --}}
        @if ($this->jobs->hasPages())
            <div class="px-4 py-3 border-t border-white/40 dark:border-white/[0.08]">
                {{ $this->jobs->links() }}
            </div>
        @endif

    </div>

</div>
