@php
    $isExpanded = in_array($node->id, $expandedNodes);
    $hasChildren = $node->children->isNotEmpty();
    $indent = $depth * 20;
    $statusClass = match ($node->status) {
        \App\Enums\LocationStatus::Active  => 'badge-green',
        \App\Enums\LocationStatus::Blocked => 'badge-red',
        \App\Enums\LocationStatus::Full    => 'badge-amber',
    };
@endphp

<div wire:key="tree-{{ $node->id }}" class="select-none">

    <div class="flex items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-white/40 dark:hover:bg-white/[0.05]"
         style="padding-left: {{ 8 + $indent }}px">

        {{-- Expand toggle --}}
        @if ($hasChildren)
            <button type="button"
                    wire:click="toggleExpand({{ $node->id }})"
                    class="flex-shrink-0 w-4 h-4 flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition">
                @if ($isExpanded)
                    <x-ui.icon name="x" class="w-3 h-3 rotate-45" />
                @else
                    <x-ui.icon name="plus" class="w-3 h-3" />
                @endif
            </button>
        @else
            <span class="w-4 flex-shrink-0"></span>
        @endif

        {{-- Code --}}
        <span class="font-mono text-xs font-semibold text-slate-800 dark:text-slate-100 min-w-0 truncate">
            {{ $node->code }}
        </span>

        {{-- Level badge --}}
        <span class="hidden sm:inline-flex shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide
                     bg-slate-100 dark:bg-white/[0.08] text-slate-500 dark:text-slate-400
                     ring-1 ring-slate-200 dark:ring-white/[0.08]">
            {{ $node->level->label() }}
        </span>

        {{-- Status --}}
        <span class="badge {{ $statusClass }} shrink-0">{{ $node->status->label() }}</span>

        {{-- Spacer --}}
        <span class="flex-1"></span>

        {{-- Actions --}}
        @can('locations.update')
            <a href="{{ route('admin.locations.edit', $node) }}"
               wire:navigate
               class="icon-btn hover:text-simba-600 dark:hover:text-simba-400 shrink-0"
               title="Edit">
                <x-ui.icon name="cog" class="h-3.5 w-3.5" />
            </a>
        @endcan

    </div>

    {{-- Children --}}
    @if ($hasChildren && $isExpanded)
        <div>
            @foreach ($node->children as $child)
                @include('livewire.admin.locations._tree-node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif

</div>
