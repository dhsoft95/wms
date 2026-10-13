<div class="space-y-6">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">{{ $import->type->label() }} Import</h2>
            <p class="page-sub font-mono text-xs">{{ $import->original_filename }}</p>
        </div>
        <a href="{{ route('admin.imports.index') }}" wire:navigate class="btn btn-ghost text-sm">← Back</a>
    </div>

    {{-- Status Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="card p-4 text-center">
            <p class="text-xs text-slate-500">Status</p>
            <span class="mt-1 inline-flex badge badge-{{ $import->status->color() }}">{{ $import->status->label() }}</span>
        </div>
        <div class="card p-4 text-center">
            <p class="text-xs text-slate-500">Total Rows</p>
            <p class="mt-1 text-xl font-bold tabular-nums">{{ number_format($import->total_rows) }}</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-xs text-slate-500">Errors</p>
            <p class="mt-1 text-xl font-bold tabular-nums {{ $import->error_rows > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($import->error_rows) }}</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-xs text-slate-500">Created / Updated</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-green-600">{{ number_format($import->created_count) }} / {{ number_format($import->updated_count) }}</p>
        </div>
    </div>

    {{-- Summary cards (review step) --}}
    @if ($import->summary)
        @php $s = $import->summary; @endphp
        @if (isset($s['lines']))
            {{-- Opening stock summary --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="card p-4 text-center">
                    <p class="text-xs text-slate-500">Lines</p>
                    <p class="mt-1 text-xl font-bold tabular-nums">{{ number_format($s['lines']) }}</p>
                </div>
                <div class="card p-4 text-center">
                    <p class="text-xs text-slate-500">Total Qty</p>
                    <p class="mt-1 text-xl font-bold tabular-nums">{{ number_format((float)($s['total_qty'] ?? 0), 0) }}</p>
                </div>
                <div class="card p-4 text-center">
                    <p class="text-xs text-slate-500">Serials</p>
                    <p class="mt-1 text-xl font-bold tabular-nums">{{ number_format($s['serial_count'] ?? 0) }}</p>
                </div>
                <div class="card p-4 text-center">
                    <p class="text-xs text-slate-500">STK Transactions</p>
                    <p class="mt-1 text-xl font-bold tabular-nums">{{ number_format($s['transactions'] ?? 0) }}</p>
                </div>
            </div>
        @else
            {{-- Generic import summary --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="card p-4 text-center">
                    <p class="text-xs text-slate-500">Will Create</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-green-600">{{ number_format($s['will_create'] ?? 0) }}</p>
                </div>
                <div class="card p-4 text-center">
                    <p class="text-xs text-slate-500">Will Update</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-slate-600 dark:text-slate-400">{{ number_format($s['will_update'] ?? 0) }}</p>
                </div>
                <div class="card p-4 text-center">
                    <p class="text-xs text-slate-500">Errors</p>
                    <p class="mt-1 text-xl font-bold tabular-nums {{ ($s['errors'] ?? 0) > 0 ? 'text-red-600' : 'text-slate-400' }}">{{ number_format($s['errors'] ?? 0) }}</p>
                </div>
            </div>
        @endif
    @endif

    {{-- Ready-to-import action banner --}}
    @if ($import->canConfirm())
        <div class="card p-4 flex items-center gap-4 border-green-500/30 bg-green-50/40 dark:bg-green-950/20">
            <x-ui.icon name="badge-check" class="w-6 h-6 text-green-600 shrink-0" />
            <div class="flex-1">
                <p class="font-semibold text-sm text-green-700 dark:text-green-300">Validation passed — ready to import</p>
                <p class="text-xs text-green-600 dark:text-green-400">
                    {{ number_format($import->valid_rows) }} row(s) will be processed.
                    @if ($import->error_rows > 0)
                        <span class="text-amber-600 dark:text-amber-400">{{ number_format($import->error_rows) }} row(s) will be skipped (errors).</span>
                    @endif
                </p>
            </div>
            <button type="button" wire:click="confirm"
                    wire:confirm="Are you sure you want to run this import? This cannot be undone."
                    class="btn btn-primary">
                Confirm Import
            </button>
        </div>
    @endif

    @if ($import->status->value === 'completed')
        <div class="card p-4 flex items-center gap-4 border-emerald-500/30 bg-emerald-50/40 dark:bg-emerald-950/20">
            <x-ui.icon name="badge-check" class="w-6 h-6 text-emerald-600 shrink-0" />
            <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                Import complete — {{ number_format($import->created_count) }} created, {{ number_format($import->updated_count) }} updated.
            </p>
        </div>
    @endif

    {{-- Error download --}}
    @if ($import->error_rows > 0)
        <div class="card p-4 flex items-center gap-4">
            <div class="flex-1">
                <p class="font-semibold text-sm">{{ number_format($import->error_rows) }} row(s) had errors</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Download the original file with an added "Errors" column to fix and re-upload.</p>
            </div>
            <button type="button" wire:click="downloadErrors" class="btn btn-secondary text-sm">
                <x-ui.icon name="download" class="w-4 h-4" /> Download Error Report
            </button>
        </div>
    @endif

    {{-- In-progress auto-refresh --}}
    @if (in_array($import->status->value, ['uploaded', 'validating', 'importing']))
        <div wire:poll.3s class="card p-4 flex items-center gap-3 text-sm text-brand dark:text-simba-400">
            <x-ui.icon name="refresh" class="w-4 h-4 animate-spin" />
            {{ $import->status->label() }} — refreshing automatically…
        </div>
    @endif

    {{-- Validation Error Table --}}
    @if (! empty($this->previewErrors))
        <div class="card overflow-hidden">
            <div class="p-4 border-b border-white/40 dark:border-white/[0.08]">
                <h3 class="font-semibold text-sm text-red-700 dark:text-red-400">
                    Validation Errors
                    <span class="ml-2 text-slate-400 font-normal text-xs">(first 50 shown — download report for all)</span>
                </h3>
            </div>
            <x-ui.table>
                    <thead>
                        <tr>
                            <th class="px-4 py-3 w-20">Row</th>
                            <th class="px-4 py-3 w-40">Column</th>
                            <th class="px-4 py-3">Message</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
                        @foreach ($this->previewErrors as $e)
                            <tr>
                                <td class="px-4 py-3 tabular-nums text-sm">{{ $e['row'] }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $e['column'] }}</td>
                                <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400">{{ $e['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
            </x-ui.table>
        </div>
    @endif

    {{-- Valid rows preview --}}
    @if (! empty($this->previewRows) && in_array($import->status->value, ['validated', 'completed']))
        @php $cols = array_keys($this->previewRows[0] ?? []); @endphp
        <div class="card overflow-hidden">
            <div class="p-4 border-b border-white/40 dark:border-white/[0.08] flex items-center justify-between">
                <h3 class="font-semibold text-sm">
                    Valid Rows Preview
                    <span class="ml-2 text-slate-400 font-normal text-xs">(first 50 valid rows shown)</span>
                </h3>
                <span class="text-xs text-slate-500">{{ number_format($import->valid_rows) }} total valid</span>
            </div>
            <x-ui.table class="text-xs">
                    <thead>
                        <tr>
                            @foreach ($cols as $col)
                                <th class="px-3 py-2">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
                        @foreach ($this->previewRows as $row)
                            <tr class="hover:bg-white/30 dark:hover:bg-white/[0.04]">
                                @foreach ($cols as $col)
                                    <td class="px-3 py-2 font-mono truncate max-w-[200px]" title="{{ $row[$col] ?? '' }}">
                                        {{ $row[$col] ?? '' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
            </x-ui.table>
        </div>
    @endif

</div>
