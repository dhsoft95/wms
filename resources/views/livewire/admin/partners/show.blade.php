<div class="space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-col gap-5 pb-6 sm:flex-row sm:items-center sm:justify-between
                border-b border-white/40 dark:border-white/[0.08]">

        <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="font-mono text-sm font-semibold text-simba-700 dark:text-simba-400">
                    {{ $partner->code }}
                </span>
                @if ($partner->is_active)
                    <span class="badge badge-green"><span class="dot"></span> Active</span>
                @else
                    <span class="badge badge-slate"><span class="dot"></span> Inactive</span>
                @endif
            </div>
            <h2 class="mt-1 truncate text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                {{ $partner->name }}
            </h2>
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach ((array) $partner->type as $type)
                    <span class="rounded bg-slate-100 dark:bg-white/[0.08] px-1.5 py-0.5
                                 text-[10px] font-semibold uppercase tracking-wide
                                 text-slate-600 dark:text-slate-300
                                 ring-1 ring-slate-200 dark:ring-white/[0.1]">
                        {{ \App\Enums\PartnerType::from($type)->label() }}
                    </span>
                @endforeach
            </div>
        </div>

        {{-- Header Actions --}}
        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            <a href="{{ route('admin.partners.index') }}" wire:navigate class="btn btn-secondary btn-sm">
                <x-ui.icon name="arrows" class="h-4 w-4" /> Back
            </a>

            @can('partners.update')
                <a href="{{ route('admin.partners.edit', $partner) }}" wire:navigate class="btn btn-secondary">
                    <x-ui.icon name="cog" class="h-4 w-4" /> Edit Partner
                </a>
            @endcan
        </div>
    </div>


    {{-- Main Content: Two Columns --}}
    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">

        {{-- LEFT COLUMN --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- Identity Card --}}
            <section class="card overflow-hidden">
                <div class="card-header sm:px-7 py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl
                                    bg-simba-50 dark:bg-simba-500/15
                                    text-simba-600 dark:text-simba-400">
                            <x-ui.icon name="user" class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Identity</h3>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Partner identification and tax information</p>
                        </div>
                    </div>
                </div>

                <dl class="divide-y divide-white/50 dark:divide-white/[0.06] px-5 sm:px-7">

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Code</dt>
                        <dd class="text-sm font-mono font-semibold text-slate-800 dark:text-slate-200">{{ $partner->code }}</dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Name</dt>
                        <dd class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $partner->name }}</dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Type(s)</dt>
                        <dd class="flex flex-wrap gap-1">
                            @foreach ((array) $partner->type as $type)
                                <span class="rounded bg-slate-100 dark:bg-white/[0.08] px-1.5 py-0.5
                                             text-[10px] font-semibold uppercase tracking-wide
                                             text-slate-600 dark:text-slate-300
                                             ring-1 ring-slate-200 dark:ring-white/[0.1]">
                                    {{ \App\Enums\PartnerType::from($type)->label() }}
                                </span>
                            @endforeach
                        </dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">TIN</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300 mono">{{ $partner->tin ?? '—' }}</dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">VRN</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300 mono">{{ $partner->vrn ?? '—' }}</dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Status</dt>
                        <dd>
                            @if ($partner->is_active)
                                <span class="badge badge-green">Active</span>
                            @else
                                <span class="badge badge-slate">Inactive</span>
                            @endif
                        </dd>
                    </div>

                </dl>
            </section>


            {{-- Contact Card --}}
            <section class="card overflow-hidden">
                <div class="card-header sm:px-7 py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl
                                    bg-simba-50 dark:bg-simba-500/15
                                    text-simba-600 dark:text-simba-400">
                            <x-ui.icon name="phone" class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Contact</h3>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Contact person and communication details</p>
                        </div>
                    </div>
                </div>

                <dl class="divide-y divide-white/50 dark:divide-white/[0.06] px-5 sm:px-7">

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Contact Person</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ $partner->contact_person ?? '—' }}</dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Phone</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300 mono">{{ $partner->phone ?? '—' }}</dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[180px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Email</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300 mono break-all">{{ $partner->email ?? '—' }}</dd>
                    </div>

                </dl>
            </section>

        </div>


        {{-- RIGHT COLUMN: Address & Meta --}}
        <aside class="space-y-5">

            {{-- Address Card --}}
            <section class="card overflow-hidden">
                <div class="card-header py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl
                                    bg-simba-50 dark:bg-simba-500/15
                                    text-simba-600 dark:text-simba-400">
                            <x-ui.icon name="arrows" class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Address</h3>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Location information</p>
                        </div>
                    </div>
                </div>

                <dl class="divide-y divide-white/50 dark:divide-white/[0.06] px-5">

                    <div class="py-4">
                        <dt class="section-label mb-1">Address</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap">
                            {{ $partner->address ?? '—' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[90px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">City</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ $partner->city ?? '—' }}</dd>
                    </div>

                    <div class="grid grid-cols-1 gap-2 py-4 sm:grid-cols-[90px_minmax(0,1fr)] sm:items-center">
                        <dt class="section-label">Country</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ $partner->country ?? '—' }}</dd>
                    </div>

                </dl>
            </section>


            {{-- Meta Card --}}
            <section class="card overflow-hidden">
                <div class="card-header py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl
                                    bg-simba-50 dark:bg-simba-500/15
                                    text-simba-600 dark:text-simba-400">
                            <x-ui.icon name="refresh" class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Record Info</h3>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Created and modified timestamps</p>
                        </div>
                    </div>
                </div>

                <dl class="divide-y divide-white/50 dark:divide-white/[0.06] px-5">

                    <div class="py-4">
                        <dt class="section-label mb-1">Created</dt>
                        <dd class="text-sm font-medium text-slate-800 dark:text-slate-200">
                            {{ $partner->created_at->format('d M Y, H:i') }}
                        </dd>
                        <dd class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                            {{ $partner->created_at->diffForHumans() }}
                        </dd>
                    </div>

                    <div class="py-4">
                        <dt class="section-label mb-1">Last Updated</dt>
                        <dd class="text-sm font-medium text-slate-800 dark:text-slate-200">
                            {{ $partner->updated_at->format('d M Y, H:i') }}
                        </dd>
                        <dd class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
                            {{ $partner->updated_at->diffForHumans() }}
                        </dd>
                    </div>

                </dl>
            </section>

        </aside>
    </div>

</div>
