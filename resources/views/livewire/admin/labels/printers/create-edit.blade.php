<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">{{ $isEdit ? 'Edit Printer' : 'New Printer' }}</h2>
            <p class="page-sub">{{ $isEdit ? 'Update printer configuration.' : 'Configure a new printer for label output.' }}</p>
        </div>
        <a href="{{ route('admin.printers.index') }}" wire:navigate class="btn btn-secondary">
            <x-ui.icon name="x" class="w-4 h-4" /> Cancel
        </a>
    </div>

    <form wire:submit="save" class="max-w-2xl space-y-6">

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="printer" class="w-4 h-4" />
                    Printer Details
                </h3>
            </div>

            <div class="card-body space-y-5">

                {{-- Name --}}
                <div>
                    <label class="label" for="pr-name">Name <span class="required-mark">*</span></label>
                    <input
                        wire:model="name"
                        id="pr-name"
                        type="text"
                        placeholder="e.g. Warehouse Floor Zebra"
                        class="input w-full @error('name') border-red-400 @enderror"
                    />
                    @error('name') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>

                {{-- Kind --}}
                <div>
                    <label class="label" for="pr-kind">Kind <span class="required-mark">*</span></label>
                    <select
                        wire:model.live="kind"
                        id="pr-kind"
                        class="input w-full @error('kind') border-red-400 @enderror"
                    >
                        @foreach ($printerKinds as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </select>
                    @error('kind') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>

                {{-- Network fields (only for zpl_network) --}}
                @if ($this->isNetworkPrinter())
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label" for="pr-ip">IP Address <span class="required-mark">*</span></label>
                            <input
                                wire:model="ip"
                                id="pr-ip"
                                type="text"
                                placeholder="192.168.1.100"
                                class="input w-full mono @error('ip') border-red-400 @enderror"
                            />
                            @error('ip') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="pr-port">Port <span class="required-mark">*</span></label>
                            <input
                                wire:model="port"
                                id="pr-port"
                                type="number"
                                min="1"
                                max="65535"
                                class="input w-full @error('port') border-red-400 @enderror"
                            />
                            @error('port') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif

                {{-- DPI --}}
                <div>
                    <label class="label">Resolution (DPI) <span class="required-mark">*</span></label>
                    <div class="flex gap-4 mt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input wire:model="dpi" type="radio" value="203" class="text-simba-600 focus:ring-simba-600" />
                            <span class="text-sm text-slate-700 dark:text-slate-300">203 DPI (standard)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input wire:model="dpi" type="radio" value="300" class="text-simba-600 focus:ring-simba-600" />
                            <span class="text-sm text-slate-700 dark:text-slate-300">300 DPI (high quality)</span>
                        </label>
                    </div>
                    @error('dpi') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>

                {{-- Location Note --}}
                <div>
                    <label class="label" for="pr-loc">
                        Location Note <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span>
                    </label>
                    <input
                        wire:model="locationNote"
                        id="pr-loc"
                        type="text"
                        placeholder="e.g. Inbound dock, Rack A3"
                        class="input w-full @error('locationNote') border-red-400 @enderror"
                    />
                    @error('locationNote') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>

                {{-- Active --}}
                <label class="flex items-start gap-3 cursor-pointer">
                    <div class="pt-0.5">
                        <input
                            wire:model="isActive"
                            type="checkbox"
                            class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800"
                        />
                    </div>
                    <div>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Active</span>
                        <p class="hint mt-0.5">Only active printers appear in print dialogs.</p>
                    </div>
                </label>

            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
            <a href="{{ route('admin.printers.index') }}" wire:navigate class="btn btn-secondary text-center">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary justify-center">
                <x-ui.icon name="save" class="w-4 h-4" /> {{ $isEdit ? 'Update Printer' : 'Create Printer' }}
            </button>
        </div>

    </form>

</div>
