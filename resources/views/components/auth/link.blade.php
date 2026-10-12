@props(['href' => '#'])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'font-semibold text-simba-600 dark:text-simba-300 hover:text-simba-700 dark:hover:text-simba-200 hover:underline transition-colors duration-150 text-sm']) }}>{{ $slot }}</a>
