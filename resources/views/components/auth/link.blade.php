@props(['href' => '#'])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'font-semibold text-simba-600 hover:text-simba-700 transition-colors duration-150 text-sm']) }}>{{ $slot }}</a>
