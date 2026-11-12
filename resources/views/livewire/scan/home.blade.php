@php
$greeting = match(true) {
    now()->hour < 12 => 'Good morning',
    now()->hour < 17 => 'Good afternoon',
    default          => 'Good evening',
};

$operations = [
    ['permission' => 'scan.receive',  'route' => 'scan.receive',  'icon' => 'inbox',          'label' => 'Receive',    'desc' => 'Scan incoming goods'],
    ['permission' => 'scan.putaway',  'route' => 'scan.putaway',  'icon' => 'archive',        'label' => 'Putaway',    'desc' => 'Assign bin location'],
    ['permission' => 'scan.lookup',   'route' => 'scan.lookup',   'icon' => 'search',         'label' => 'Lookup',     'desc' => 'Check stock, bin or serial'],
    ['permission' => 'scan.pick',     'route' => 'scan.pick',     'icon' => 'clipboard-list', 'label' => 'Pick',       'desc' => 'Fulfil a pick list'],
    ['permission' => 'scan.dispatch', 'route' => 'scan.dispatch', 'icon' => 'truck',          'label' => 'Dispatch',   'desc' => 'Process outbound shipment'],
    ['permission' => 'scan.gate',     'route' => 'scan.gate',     'icon' => 'check',          'label' => 'Gate Check', 'desc' => 'Verify vehicle exit'],
];

$visible = array_filter($operations, fn ($op) => auth()->user()->can($op['permission']));
@endphp

<div class="space-y-5">

    {{-- Greeting card --}}
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-5 py-4 shadow-sm">
        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $greeting }}</p>
        <p class="mt-0.5 text-sm text-slate-400">{{ now()->format('l, d M Y') }} &middot; {{ now()->format('H:i') }}</p>
    </div>

    {{-- Operations grid --}}
    @if (count($visible) > 0)
    <div>
        <p class="mb-3 px-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Scan Operations</p>

        <div class="grid grid-cols-2 gap-3">
            @foreach ($visible as $op)
            <a href="{{ route($op['route']) }}" wire:navigate
               class="group flex min-h-[120px] flex-col gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm transition
                      hover:border-simba-200 dark:hover:border-simba-900/60 hover:shadow-md
                      active:scale-[0.97]
                      focus:outline-none focus-visible:ring-2 focus-visible:ring-simba-600">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-simba-50 text-simba-600 dark:bg-simba-950/40 dark:text-simba-300 transition group-hover:bg-simba-100 dark:group-hover:bg-simba-950/60">
                    <x-ui.icon name="{{ $op['icon'] }}" class="size-5" />
                </span>
                <div class="min-w-0">
                    <p class="font-bold text-slate-900 dark:text-slate-100">{{ $op['label'] }}</p>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $op['desc'] }}</p>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @else
    <p class="text-center text-sm text-slate-400 py-8">No scan operations are available for your account.</p>
    @endif

</div>
