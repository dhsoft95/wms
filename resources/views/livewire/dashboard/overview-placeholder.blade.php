<div class="space-y-4">

    {{-- Greeting card skeleton --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-simba-700 via-simba-600 to-simba-500 px-6 py-5">
        <div class="pointer-events-none absolute -right-8 -top-8 size-40 rounded-full bg-white/10"></div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1.5">
                <div class="h-3.5 w-32 rounded bg-white/20 animate-pulse"></div>
                <div class="h-7 w-56 rounded bg-white/20 animate-pulse"></div>
                <div class="h-3.5 w-48 rounded bg-white/15 animate-pulse"></div>
            </div>
            <div class="h-9 w-40 rounded-xl bg-white/15 animate-pulse"></div>
        </div>
    </div>

    {{-- Page header skeleton --}}
    <div class="page-header">
        <div class="h-7 w-44 rounded bg-slate-200 dark:bg-white/10 animate-pulse"></div>
        <div class="flex gap-2 ml-auto">
            <div class="h-8 w-32 rounded-md bg-slate-200 dark:bg-white/10 animate-pulse"></div>
            <div class="h-8 w-24 rounded-lg bg-slate-200 dark:bg-white/10 animate-pulse"></div>
        </div>
    </div>

    {{-- KPI skeleton grid --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3 auto-rows-fr">
        @for ($i = 0; $i < $kpiCount; $i++)
            <x-ui.kpi-card loading />
        @endfor
    </div>

    {{-- Charts skeleton --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="card" style="min-height:280px;">
            <div class="card-header">
                <div class="h-7 w-48 rounded-lg bg-slate-200 dark:bg-white/10 animate-pulse"></div>
            </div>
            <div class="card-body flex items-center justify-center">
                <div class="w-full h-48 rounded-lg bg-slate-100 dark:bg-white/5 animate-pulse"></div>
            </div>
        </div>
        <div class="card" style="min-height:280px;">
            <div class="card-header">
                <div class="h-5 w-32 rounded bg-slate-200 dark:bg-white/10 animate-pulse"></div>
            </div>
            <div class="card-body flex items-center justify-center">
                <div class="w-full h-48 rounded-lg bg-slate-100 dark:bg-white/5 animate-pulse"></div>
            </div>
        </div>
    </div>

    {{-- Top moving items skeleton --}}
    <div class="card">
        <div class="card-header">
            <div class="h-5 w-36 rounded bg-slate-200 dark:bg-white/10 animate-pulse"></div>
        </div>
        <div class="card-body">
            <div class="w-full h-48 rounded-lg bg-slate-100 dark:bg-white/5 animate-pulse"></div>
        </div>
    </div>

</div>
