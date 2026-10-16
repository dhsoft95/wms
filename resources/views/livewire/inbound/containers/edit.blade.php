<div class="space-y-3">
    <div class="page-header">
        <div>
            <a href="{{ route('inbound.containers.show', $container) }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to container
            </a>
            <div class="flex items-center gap-3 flex-wrap">
                <h2 class="page-title font-mono">{{ $container->container_no }}</h2>
                <span class="badge {{ $container->status->color() }}">
                    <span class="dot"></span>{{ $container->status->label() }}
                </span>
            </div>
            <p class="page-sub text-slate-400 font-mono text-xs mt-1">{{ $container->number }}</p>
        </div>
        <a href="{{ route('inbound.containers.show', $container) }}" wire:navigate class="btn btn-ghost">Cancel</a>
    </div>

    @if(!$container->isEditable())
        <div class="card p-4 border-l-4 border-amber-400">
            <div class="flex gap-3">
                <x-ui.icon name="alert" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                <div>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Limited editing</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">This container is in <strong>{{ $container->status->label() }}</strong> status. Most fields are read-only.</p>
                </div>
            </div>
        </div>
    @endif

    <form wire:submit="save" class="space-y-3">
        @include('livewire.inbound.containers._form', ['container' => $container])

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('inbound.containers.show', $container) }}" wire:navigate class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                <x-ui.icon name="check" class="w-4 h-4" wire:loading.remove wire:target="save" />
                <x-ui.icon name="refresh" class="w-4 h-4 animate-spin" wire:loading wire:target="save" />
                Save Changes
            </button>
        </div>
    </form>
</div>
