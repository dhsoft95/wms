@props([
    'icon'  => 'archive',
    'title' => 'No data',
    'text'  => null,
    'cols'  => 999,
])
<tr>
    <td colspan="{{ $cols }}" class="py-14 text-center">
        <div class="flex flex-col items-center gap-2">
            <x-ui.icon :name="$icon" class="w-8 h-8 text-slate-300 dark:text-slate-600" />
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-400">{{ $title }}</p>
            @if ($text)
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">{{ $text }}</p>
            @endif
        </div>
    </td>
</tr>
