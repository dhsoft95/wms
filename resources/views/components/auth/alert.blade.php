@props(['status' => null, 'type' => 'info'])
@if ($status)
    @php
        $styles = match($type) {
            'success' => 'border-l-emerald-500 text-emerald-800 dark:text-emerald-300',
            'error'   => 'border-l-red-500 text-red-800 dark:text-red-300',
            'warning' => 'border-l-amber-500 text-amber-800 dark:text-amber-300',
            default   => 'border-l-blue-500 text-blue-800 dark:text-blue-300',
        };
        $iconPath = match($type) {
            'success' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            'error'   => 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z',
            'warning' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
            default   => 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z',
        };
    @endphp
    <div class="glass dark:bg-zinc-900/60 dark:border-white/15 flex items-start gap-3 p-3.5 rounded-xl border-l-4 {{ $styles }} text-sm" role="alert">
        <svg class="w-[18px] h-[18px] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/>
        </svg>
        <span>{{ $status }}</span>
    </div>
@endif
