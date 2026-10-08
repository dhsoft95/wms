@props(['title', 'description' => null])
<div class="mb-6 sm:mb-8">
    <div class="h-1 w-10 sm:w-12 rounded-full bg-simba-600 mb-4 sm:mb-5" aria-hidden="true"></div>
    <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $title }}</h2>
    @if ($description)
        <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400 leading-relaxed">{{ $description }}</p>
    @endif
</div>
