<div class="relative" x-data @click.outside="$wire.open = false">
    <button wire:click="toggle" class="icon-btn relative" title="Notifications">
        <x-ui.icon name="bell" class="w-5 h-5" />
        @if ($this->unreadCount > 0)
            <span class="absolute top-2 right-2 w-2 h-2 rounded-full bg-simba-600 ring-2 ring-white/50 dark:ring-zinc-900/50"></span>
        @endif
    </button>

    @if ($open)
        <div class="absolute right-0 mt-2 w-80 card p-0 overflow-hidden z-50 shadow-xl"
             style="max-height: 420px; overflow-y: auto;">
            <div class="flex items-center justify-between px-4 py-3 border-b border-white/40 dark:border-white/[0.08] sticky top-0 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-sm">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">
                    Notifications
                    @if($this->unreadCount > 0)
                        <span class="badge badge-red ml-1 text-[10px]">{{ $this->unreadCount }}</span>
                    @endif
                </h4>
                @if ($this->unreadCount > 0)
                    <button wire:click="markAllRead" class="text-xs text-brand hover:underline">Mark all read</button>
                @endif
            </div>
            @forelse ($this->notifications as $n)
                @php $data = $n->data; @endphp
                <div wire:key="notif-{{ $n->id }}"
                     class="px-4 py-3 border-b border-white/20 dark:border-white/[0.05] hover:bg-zinc-900/5 dark:hover:bg-white/[0.04] cursor-pointer transition-colors"
                     @if (!empty($data['container_id']))
                        x-on:click="$wire.markRead('{{ $n->id }}'); window.location = '{{ route('inbound.containers.show', ':id') }}'.replace(':id', {{ $data['container_id'] }});"
                     @else
                        wire:click="markRead('{{ $n->id }}')"
                     @endif>
                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">{{ $data['title'] ?? 'Notification' }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-snug">{{ $data['message'] ?? '' }}</p>
                    <p class="text-[11px] text-slate-400 mt-1">{{ $n->created_at->diffForHumans() }}</p>
                </div>
            @empty
                <div class="px-4 py-8 text-center">
                    <x-ui.icon name="bell" class="w-8 h-8 mx-auto text-slate-300 dark:text-slate-600" />
                    <p class="mt-2 text-sm text-slate-400">No unread notifications</p>
                </div>
            @endforelse
        </div>
    @endif
</div>
