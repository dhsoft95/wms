<div class="space-y-4">
    <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm border border-slate-200 dark:border-slate-800">
        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Logged in as</p>
        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ auth()->user()->name }}</p>
        <p class="text-sm text-slate-500">{{ now()->format('D, d M Y · H:i') }}</p>
    </div>

    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 px-1">Scan Operations</p>

    <a href="{{ route('scan.receive') }}" wire:navigate class="scan-btn scan-btn-primary">
        <x-ui.icon name="inbox" class="w-7 h-7" />
        <div class="text-left">
            <p class="font-bold">Receive</p>
            <p class="text-sm text-simba-200 font-normal">Scan incoming goods</p>
        </div>
    </a>

    <a href="{{ route('scan.putaway') }}" wire:navigate class="scan-btn scan-btn-secondary">
        <x-ui.icon name="archive" class="w-7 h-7 text-simba-600" />
        <div class="text-left">
            <p class="font-bold">Putaway</p>
            <p class="text-sm text-slate-400 font-normal">Assign bin location</p>
        </div>
    </a>

    <a href="{{ route('scan.pick') }}" wire:navigate class="scan-btn scan-btn-secondary">
        <x-ui.icon name="clipboard" class="w-7 h-7 text-simba-600" />
        <div class="text-left">
            <p class="font-bold">Pick</p>
            <p class="text-sm text-slate-400 font-normal">Fulfil a pick list</p>
        </div>
    </a>

    <a href="{{ route('scan.stock-lookup') }}" wire:navigate class="scan-btn scan-btn-secondary">
        <x-ui.icon name="search" class="w-7 h-7 text-simba-600" />
        <div class="text-left">
            <p class="font-bold">Stock Lookup</p>
            <p class="text-sm text-slate-400 font-normal">Check quantity and location</p>
        </div>
    </a>
</div>
