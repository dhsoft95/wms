<div class="space-y-3">
    <div class="page-header">
        <div>
            <a href="{{ route('inbound.containers.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-simba-600 mb-2">
                <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Back to containers
            </a>
            <h2 class="page-title">Register Container</h2>
            <p class="page-sub">Add a new inbound container to track from arrival through unloading.</p>
        </div>
        <a href="{{ route('inbound.containers.index') }}" wire:navigate class="btn btn-ghost">Cancel</a>
    </div>

    <form wire:submit="save" class="space-y-3">
        @include('livewire.inbound.containers._form')

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('inbound.containers.index') }}" wire:navigate class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                <x-ui.icon name="check" class="w-4 h-4" wire:loading.remove wire:target="save" />
                <x-ui.icon name="refresh" class="w-4 h-4 animate-spin" wire:loading wire:target="save" />
                Save Container
            </button>
        </div>
    </form>
</div>
