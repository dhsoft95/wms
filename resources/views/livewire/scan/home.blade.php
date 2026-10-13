<div class="space-y-4">
    <div class="rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-sm border border-slate-200 dark:border-slate-800">
        <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Logged in as</p>
        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ auth()->user()->name }}</p>
        <p class="text-sm text-slate-400">{{ session('scan_device_code', 'Unknown Device') }} &middot; {{ now()->format('D, d M Y · H:i') }}</p>
    </div>

    <div class="flex items-center justify-between px-1">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Scan Operations</p>
        <button x-data="{muted: localStorage.getItem('scan_muted')==='1'}"
                @click="muted=!muted; localStorage.setItem('scan_muted',muted?'1':'0'); ScanFeedback.muted=muted"
                class="text-xs text-slate-400 hover:text-slate-600 flex items-center gap-1">
            <x-ui.icon name="volume-2" class="w-4 h-4" />
            <span x-text="muted ? 'Sound off' : 'Sound on'">Sound on</span>
        </button>
    </div>

    @if(auth()->user()->can('scan.receive'))
    <a href="{{ route('scan.receive') }}" wire:navigate class="scan-btn scan-btn-primary">
        <x-ui.icon name="inbox" class="w-7 h-7" />
        <div class="text-left">
            <p class="font-bold">Receive</p>
            <p class="text-sm text-simba-200 font-normal">Scan incoming goods</p>
        </div>
    </a>
    @endif

    @if(auth()->user()->can('scan.putaway'))
    <a href="{{ route('scan.putaway') }}" wire:navigate class="scan-btn scan-btn-secondary">
        <x-ui.icon name="archive" class="w-7 h-7 text-simba-600" />
        <div class="text-left">
            <p class="font-bold">Putaway</p>
            <p class="text-sm text-slate-400 font-normal">Assign bin location</p>
        </div>
    </a>
    @endif

    @if(auth()->user()->can('scan.move'))
    <a href="{{ route('scan.move') }}" wire:navigate class="scan-btn scan-btn-secondary">
        <x-ui.icon name="arrows" class="w-7 h-7 text-simba-600" />
        <div class="text-left">
            <p class="font-bold">Move</p>
            <p class="text-sm text-slate-400 font-normal">Relocate pallet or items</p>
        </div>
    </a>
    @endif

    @if(auth()->user()->can('scan.lookup'))
    <a href="{{ route('scan.lookup') }}" wire:navigate class="scan-btn scan-btn-secondary">
        <x-ui.icon name="search" class="w-7 h-7 text-simba-600" />
        <div class="text-left">
            <p class="font-bold">Lookup</p>
            <p class="text-sm text-slate-400 font-normal">Check stock, bin or serial</p>
        </div>
    </a>
    @endif

    @if(auth()->user()->can('scan.pick'))
    <div class="scan-btn scan-btn-secondary opacity-50 cursor-not-allowed">
        <x-ui.icon name="clipboard" class="w-7 h-7 text-slate-400" />
        <div class="text-left">
            <p class="font-bold text-slate-400">Pick <span class="text-xs font-normal bg-slate-200 dark:bg-slate-700 px-2 py-0.5 rounded-full ml-1">Coming soon</span></p>
            <p class="text-sm text-slate-400 font-normal">Fulfil a pick list</p>
        </div>
    </div>
    @endif
</div>
