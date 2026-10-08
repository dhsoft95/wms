<a {{ $attributes->merge(['class' => 'text-sm font-semibold text-simba-600 hover:text-simba-700 hover:underline underline-offset-2 transition']) }} wire:navigate>
    {{ $slot }}
</a>
