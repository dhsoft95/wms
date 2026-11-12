{{-- Label Print Modal — triggered by open-label-print browser event --}}
<div
    x-data="{
        lastPrinter: null,
        init() {
            try { this.lastPrinter = localStorage.getItem('wms_last_printer_id'); } catch (e) {}

            $wire.$watch('show', val => {
                if (val && this.lastPrinter && $wire.printerId === '') {
                    $wire.set('printerId', this.lastPrinter);
                }
            });
        },
        savePrinter(id) {
            try { if (id) localStorage.setItem('wms_last_printer_id', id); } catch (e) {}
        }
    }"
    @label-print-pdf.window="
        const a = document.createElement('a');
        a.href = $event.detail.url;
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    "
    @label-print-zpl.window="
        const bytes  = atob($event.detail.content);
        const arr    = new Uint8Array(bytes.length);
        for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
        const blob   = new Blob([arr], { type: 'text/plain' });
        const url    = URL.createObjectURL(blob);
        const a      = document.createElement('a');
        a.href       = url;
        a.download   = $event.detail.filename ?? 'labels.zpl';
        a.click();
        setTimeout(() => URL.revokeObjectURL(url), 5000);
    "
>
@if ($show)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-md flex flex-col max-h-[90vh]"
         @click.stop>

        {{-- Header --}}
        <div class="flex items-start justify-between px-6 pt-5 pb-4 border-b border-slate-200 dark:border-slate-800 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-simba-50 dark:bg-simba-900/30 flex items-center justify-center shrink-0">
                    <x-ui.icon name="printer" class="w-4.5 h-4.5 text-simba-600 dark:text-simba-400" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Print Labels</h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ count($ids) }} {{ Str::plural('item', count($ids)) }}
                        &middot; {{ $this->type ? \App\Enums\LabelTemplateType::from($this->type)->label() : '' }}
                    </p>
                </div>
            </div>
            <button wire:click="close" class="icon-btn mt-0.5">
                <x-ui.icon name="x" class="w-4 h-4" />
            </button>
        </div>

        {{-- Body --}}
        <div class="overflow-y-auto flex-1 px-6 py-5 space-y-4">

            {{-- Printables list --}}
            @if ($this->printables->isNotEmpty())
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden text-sm">
                <div class="max-h-32 overflow-y-auto divide-y divide-slate-100 dark:divide-white/[0.05]">
                    @foreach ($this->printables as $p)
                    <div class="flex items-center gap-2 px-3 py-2">
                        <x-ui.icon name="tag" class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span class="font-mono text-xs text-slate-700 dark:text-slate-300">
                            {{ method_exists($p, 'getBarcode') ? $p->getBarcode() : ($p->barcode ?? $p->code ?? $p->number ?? $p->getKey()) }}
                        </span>
                        @if ($p instanceof \App\Models\Inventory\Lpn && $p->print_count > 0)
                            <span class="ml-auto inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                                <x-ui.icon name="arrow-path" class="w-2.5 h-2.5" /> reprint
                            </span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Template --}}
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="label mb-0" for="lpm-template">Template <span class="required-mark">*</span></label>
                    @if ($this->locationLevelLabel)
                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                            <x-ui.icon name="map-pin" class="w-3 h-3" />
                            {{ $this->locationLevelLabel }}
                        </span>
                    @endif
                </div>
                @error('templateId') <p class="field-error mb-1">{{ $message }}</p> @enderror
                <select id="lpm-template" wire:model.live="templateId"
                        class="input w-full @error('templateId') !border-red-400 @enderror">
                    <option value="">— Select template —</option>
                    @foreach ($this->templates as $tpl)
                        <option value="{{ $tpl->id }}">
                            {{ $tpl->name }}{{ $tpl->is_default ? ' (default)' : '' }}
                        </option>
                    @endforeach
                </select>
                @if ($this->locationLevelLabel)
                    <p class="hint mt-1">Showing templates for <strong>{{ $this->locationLevelLabel }}</strong> locations.</p>
                @endif
            </div>

            {{-- Printer --}}
            <div>
                <label class="label" for="lpm-printer">Printer <span class="required-mark">*</span></label>
                @error('printerId') <p class="field-error mb-1">{{ $message }}</p> @enderror
                <select id="lpm-printer"
                        wire:model="printerId"
                        @change="savePrinter($event.target.value)"
                        class="input w-full @error('printerId') !border-red-400 @enderror">
                    <option value="">— Select printer —</option>
                    @foreach ($this->printers as $printer)
                        <option value="{{ $printer->id }}">
                            {{ $printer->name }}
                            @if ($printer->kind === \App\Enums\PrinterKind::ZplNetwork) (Network ZPL)
                            @elseif ($printer->kind === \App\Enums\PrinterKind::ZplDownload) (Download ZPL)
                            @else (PDF)
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Copies --}}
            <div>
                <label class="label" for="lpm-copies">Copies <span class="required-mark">*</span></label>
                @error('copies') <p class="field-error mb-1">{{ $message }}</p> @enderror
                <input id="lpm-copies" type="number" min="1" max="99"
                       wire:model="copies"
                       class="input w-24 tabular-nums @error('copies') !border-red-400 @enderror" />
            </div>

            {{-- Reprint reason (only when needed) --}}
            @if ($this->needsReprintReason)
            <div class="rounded-xl border border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-950/20 p-4 space-y-3">
                <div class="flex items-center gap-2 text-sm font-semibold text-amber-800 dark:text-amber-300">
                    <x-ui.icon name="exclamation-triangle" class="w-4 h-4 shrink-0" />
                    One or more labels have already been printed. A reprint reason is required.
                </div>

                <div>
                    <label class="label text-amber-700 dark:text-amber-400" for="lpm-reason">
                        Reason <span class="required-mark">*</span>
                    </label>
                    @error('reasonCodeId') <p class="field-error mb-1">{{ $message }}</p> @enderror
                    <select id="lpm-reason" wire:model="reasonCodeId"
                            class="input w-full @error('reasonCodeId') !border-red-400 @enderror">
                        <option value="">— Select reason —</option>
                        @foreach ($this->reprintReasonCodes as $rc)
                            <option value="{{ $rc->id }}">{{ $rc->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="label text-amber-700 dark:text-amber-400" for="lpm-note">Note (optional)</label>
                    <input id="lpm-note" type="text" wire:model="reprintNote" maxlength="255"
                           placeholder="Additional detail…"
                           class="input w-full" />
                </div>
            </div>
            @endif

        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-200 dark:border-slate-800 shrink-0 bg-slate-50 dark:bg-white/[0.02] rounded-b-2xl">
            <button wire:click="close" class="btn btn-ghost">Cancel</button>
            <button wire:click="confirmPrint"
                    wire:loading.attr="disabled"
                    class="btn btn-primary gap-1.5">
                <x-ui.icon name="printer" class="w-4 h-4" wire:loading.remove wire:target="confirmPrint" />
                <span wire:loading.remove wire:target="confirmPrint">Print</span>
                <span wire:loading wire:target="confirmPrint">Sending…</span>
            </button>
        </div>

    </div>
</div>
@endif
</div>
