@php $brandName = config('wms.branding.name') ?? config('app.name'); @endphp
<div class="glass-input dark:bg-zinc-900/60 dark:border-white/15 rounded-xl flex items-center gap-3 px-4 py-3 text-xs text-zinc-500 dark:text-zinc-300">
    <svg class="w-4 h-4 shrink-0 text-[color:var(--auth-link)]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
    </svg>
    <span>Secure access to <span class="font-semibold text-zinc-700 dark:text-zinc-100">{{ $brandName }}</span></span>
</div>
