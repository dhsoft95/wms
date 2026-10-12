@props([
    'title'     => '',
    'maxWidth'  => 'lg',
    'wireClose' => 'closeModal',
])
@php
$widthMap = ['sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-lg', 'xl' => 'max-w-xl', '2xl' => 'max-w-2xl'];
$w = $widthMap[$maxWidth] ?? 'max-w-lg';
@endphp

{{--
  Reusable glass modal.
  Usage:
    @if ($showMyModal)
        <x-ui.modal title="My Title" wireClose="closeMyModal" maxWidth="lg">
            ... body fields ...
            <x-slot:footer>
                <button class="btn btn-secondary" wire:click="closeMyModal">Cancel</button>
                <button class="btn btn-primary" wire:click="saveMethod">Save</button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
--}}
<div
    class="fixed inset-0 z-50 flex items-end sm:items-center justify-center sm:p-4"
    role="dialog"
    aria-modal="true"
    x-data
    x-on:keydown.escape.window="$wire.{{ $wireClose }}()"
>
    {{-- Backdrop --}}
    <div
        class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"
        wire:click="{{ $wireClose }}"
    ></div>

    {{-- Panel: bottom sheet on mobile (rounded-t), centred card on sm+ --}}
    <div class="relative z-10 w-full {{ $w }}
                rounded-t-2xl sm:rounded-2xl
                bg-white dark:bg-zinc-900
                shadow-2xl ring-1 ring-black/[0.06] dark:ring-white/[0.08]
                flex flex-col overflow-hidden
                max-h-[90dvh] sm:max-h-[85vh]">

        {{-- Header --}}
        <div class="flex shrink-0 items-center justify-between
                    px-6 py-4
                    border-b border-slate-100 dark:border-white/[0.08]">
            <h3 class="text-[15px] font-semibold text-slate-900 dark:text-white leading-snug">
                {{ $title }}
            </h3>
            <button
                type="button"
                wire:click="{{ $wireClose }}"
                class="icon-btn -mr-2 -mt-0.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                aria-label="Close"
            >
                <x-ui.icon name="x" class="h-4 w-4" />
            </button>
        </div>

        {{-- Body (scrollable) --}}
        <div class="overflow-y-auto flex-1 px-6 py-5 space-y-4">
            {{ $slot }}
        </div>

        {{-- Footer (optional) --}}
        @isset($footer)
            <div class="shrink-0 flex flex-wrap items-center justify-end gap-3
                        px-6 py-4
                        bg-slate-50/80 dark:bg-white/[0.02]
                        border-t border-slate-100 dark:border-white/[0.08]">
                {{ $footer }}
            </div>
        @endisset

    </div>
</div>
