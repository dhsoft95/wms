<div class="space-y-6">

    {{-- Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Data Import</h2>
            <p class="page-sub">Import master data and opening stock from Excel or CSV templates.</p>
        </div>
        <button type="button" wire:click="openGuide" class="btn btn-ghost text-sm gap-1.5">
            <x-ui.icon name="book-open" class="w-4 h-4" />
            Import Guide
        </button>
    </div>

    {{-- Import Guide Modal --}}
    @if ($guideModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm"
             x-data wire:click.self="closeGuide">
            <div class="card w-full max-w-lg p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-slate-900 dark:text-white">How imports work</h3>
                    <button wire:click="closeGuide" class="icon-btn"><x-ui.icon name="x" class="w-5 h-5" /></button>
                </div>
                <ol class="space-y-3 text-sm text-slate-700 dark:text-slate-300">
                    <li class="flex gap-3">
                        <span class="flex-none w-6 h-6 rounded-full bg-simba-100 dark:bg-simba-900/50 text-simba-700 dark:text-simba-300 text-xs font-bold flex items-center justify-center">1</span>
                        <span><strong>Download a template</strong> using the arrow button on each card. The template includes column instructions on a second sheet.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex-none w-6 h-6 rounded-full bg-simba-100 dark:bg-simba-900/50 text-simba-700 dark:text-simba-300 text-xs font-bold flex items-center justify-center">2</span>
                        <span><strong>Fill in your data</strong> and save as .xlsx or .csv (max 20 MB).</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex-none w-6 h-6 rounded-full bg-simba-100 dark:bg-simba-900/50 text-simba-700 dark:text-simba-300 text-xs font-bold flex items-center justify-center">3</span>
                        <span><strong>Upload the file</strong> — validation runs automatically in the background.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex-none w-6 h-6 rounded-full bg-simba-100 dark:bg-simba-900/50 text-simba-700 dark:text-simba-300 text-xs font-bold flex items-center justify-center">4</span>
                        <span><strong>Review the result</strong> — fix any errors and re-upload, or confirm to apply the data.</span>
                    </li>
                </ol>
                <p class="text-xs text-slate-500 dark:text-slate-400 border-t border-white/40 dark:border-white/[0.08] pt-4">
                    Import order matters: run Departments &amp; VWs → Item Categories → Items → Locations → Partners → Opening Stock.
                </p>
            </div>
        </div>
    @endif

    {{-- Import Type Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach ($this->importTypes as $i => $type)
            @php
                $restricted   = $type->restrictedToSystemAdmin() && !auth()->user()->hasRole('system-admin');
                $lastImport   = $this->lastImports[$type->value] ?? null;
                $stepNum      = $i + 1;
            @endphp
            <div class="card p-5 space-y-4 {{ $restricted ? 'opacity-50' : '' }}">
                {{-- Card header --}}
                <div class="flex items-start gap-3">
                    <div class="flex-none flex items-center justify-center w-9 h-9 rounded-xl bg-simba-50 dark:bg-simba-900/40 text-simba-600 dark:text-simba-400">
                        <x-ui.icon :name="$type->icon()" class="w-5 h-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 tabular-nums">STEP {{ $stepNum }}</span>
                            @if ($type->restrictedToSystemAdmin())
                                <span class="text-[10px] font-medium text-warning dark:text-warning">System Admin</span>
                            @endif
                        </div>
                        <h3 class="font-semibold text-sm text-slate-900 dark:text-white leading-tight">{{ $type->label() }}</h3>
                    </div>
                </div>

                {{-- Description --}}
                <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">{{ $type->description() }}</p>

                {{-- Last import status --}}
                @if ($lastImport)
                    <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span class="badge badge-{{ $lastImport->status->color() }} text-[10px]">{{ $lastImport->status->label() }}</span>
                        <span>{{ $lastImport->created_at?->diffForHumans() }}</span>
                        <a href="{{ route('admin.imports.show', $lastImport) }}" wire:navigate class="ml-auto text-brand hover:underline">View</a>
                    </div>
                @else
                    <div class="h-5"></div>
                @endif

                {{-- Actions --}}
                @php
                    $noTemplateTypes = ['container_packing_list', 'active_containers'];
                    $noUploadTypes   = ['container_packing_list'];
                    $hasTemplate     = !in_array($type->value, $noTemplateTypes);
                    $hasUpload       = !in_array($type->value, $noUploadTypes);
                @endphp
                @if (! $restricted)
                    <div class="flex gap-2 pt-1">
                        @if($hasUpload)
                            <button type="button"
                                    wire:click="openUpload('{{ $type->value }}')"
                                    class="btn btn-secondary flex-1 text-sm gap-1.5">
                                <x-ui.icon name="upload" class="w-4 h-4" />
                                Upload
                            </button>
                        @else
                            <span class="btn btn-secondary flex-1 text-sm opacity-40 cursor-not-allowed" title="Upload via container show page">
                                <x-ui.icon name="upload" class="w-4 h-4" /> Upload
                            </span>
                        @endif
                        @if($hasTemplate)
                            <a href="{{ route('admin.imports.template', ['type' => $type->value]) }}"
                               title="Download template"
                               class="btn btn-ghost text-sm px-2.5"
                               download>
                                <x-ui.icon name="download" class="w-4 h-4" />
                            </a>
                        @else
                            <span class="btn btn-ghost text-sm px-2.5 opacity-40 cursor-not-allowed" title="Template not available for this type">
                                <x-ui.icon name="download" class="w-4 h-4" />
                            </span>
                        @endif
                    </div>
                @else
                    <div class="flex gap-2 pt-1">
                        <button type="button" disabled class="btn btn-secondary flex-1 text-sm opacity-40 cursor-not-allowed">Upload</button>
                        <button type="button" disabled class="btn btn-ghost text-sm px-2.5 opacity-40 cursor-not-allowed">
                            <x-ui.icon name="download" class="w-4 h-4" />
                        </button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Upload Modal --}}
    @if ($uploadModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/40 backdrop-blur-sm"
             x-data wire:click.self="closeUpload">
            <div class="card w-full max-w-md p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Upload file for</p>
                        <h3 class="font-bold text-slate-900 dark:text-white">{{ \App\Enums\ImportType::from($uploadType)->label() }}</h3>
                    </div>
                    <button wire:click="closeUpload" class="icon-btn"><x-ui.icon name="x" class="w-5 h-5" /></button>
                </div>

                {{-- Mode segmented control --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-2 uppercase tracking-wide">Import Mode</label>
                    <div class="grid grid-cols-2 gap-1 p-1 rounded-lg bg-slate-100 dark:bg-zinc-800">
                        <label class="relative cursor-pointer">
                            <input type="radio" wire:model="uploadMode" value="create_only" class="sr-only peer" />
                            <span class="block text-center text-xs font-medium py-2 px-3 rounded-md transition-colors
                                         text-slate-500 dark:text-slate-400
                                         peer-checked:bg-white dark:peer-checked:bg-zinc-700 peer-checked:text-slate-900 dark:peer-checked:text-white peer-checked:shadow-sm">
                                Create Only
                            </span>
                        </label>
                        <label class="relative cursor-pointer">
                            <input type="radio" wire:model="uploadMode" value="create_or_update" class="sr-only peer" />
                            <span class="block text-center text-xs font-medium py-2 px-3 rounded-md transition-colors
                                         text-slate-500 dark:text-slate-400
                                         peer-checked:bg-white dark:peer-checked:bg-zinc-700 peer-checked:text-slate-900 dark:peer-checked:text-white peer-checked:shadow-sm">
                                Create or Update
                            </span>
                        </label>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-400 dark:text-slate-500">
                        @if ($uploadMode === 'create_only')
                            Existing records are skipped.
                        @else
                            Existing records are updated with new values.
                        @endif
                    </p>
                </div>

                {{-- File drop zone --}}
                <div x-data="{ dragging: false }"
                     @dragover.prevent="dragging = true"
                     @dragleave.prevent="dragging = false"
                     @drop.prevent="dragging = false; $el.querySelector('input[type=file]').click()"
                     :class="dragging ? 'border-brand bg-simba-50/30 dark:bg-simba-950/20' : 'border-slate-300 dark:border-zinc-600'"
                     class="relative flex flex-col items-center justify-center gap-2 border-2 border-dashed rounded-xl py-8 px-4 transition-colors cursor-pointer"
                     onclick="this.querySelector('input[type=file]').click()">
                    <x-ui.icon name="upload" class="w-8 h-8 text-slate-400" wire:loading.remove wire:target="uploadFile" />
                    <x-ui.icon name="refresh" class="w-8 h-8 text-brand animate-spin hidden" wire:loading wire:target="uploadFile" />
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300" wire:loading.remove wire:target="uploadFile">Drop file here or click to browse</p>
                    <p class="text-sm font-medium text-brand" wire:loading wire:target="uploadFile">Uploading to server…</p>
                    <p class="text-xs text-slate-400">.xlsx or .csv — max 20 MB</p>
                    <input type="file"
                           wire:model="uploadFile"
                           accept=".xlsx,.csv,.xls"
                           class="sr-only"
                           x-on:livewire-upload-error="$wire.handleUploadError()" />
                </div>
                @if ($uploadFile)
                    <p class="text-xs text-success -mt-3">
                        <x-ui.icon name="check" class="w-3 h-3 inline" /> {{ is_object($uploadFile) ? $uploadFile->getClientOriginalName() : 'File selected' }}
                    </p>
                @endif
                @if ($uploadError)
                    <p class="text-xs text-danger -mt-3">{{ $uploadError }}</p>
                @endif
                @error('uploadFile')
                    <p class="text-xs text-danger -mt-3">{{ $message }}</p>
                @enderror

                {{-- Auto-category option (items only) --}}
                @if ($uploadType === \App\Enums\ImportType::Items->value)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="autoCategory" class="rounded accent-brand border-slate-300 dark:border-zinc-600" />
                        <span class="text-sm text-slate-700 dark:text-slate-300">Auto-create missing categories</span>
                    </label>
                @endif

                <div class="flex gap-2 justify-end pt-1">
                    <button type="button" wire:click="closeUpload" class="btn btn-ghost">Cancel</button>
                    <button type="button" wire:click="upload" wire:loading.attr="disabled" class="btn btn-primary">
                        <span wire:loading wire:target="upload">Uploading…</span>
                        <span wire:loading.remove wire:target="upload">Upload &amp; Validate</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Import History --}}
    <div class="card overflow-hidden">
        <div class="p-4 border-b border-white/40 dark:border-white/[0.08] flex items-center gap-3 flex-wrap">
            <h3 class="font-semibold text-sm text-slate-900 dark:text-white flex-1">Import History</h3>

            {{-- Filters --}}
            <select wire:model.live="filterType" class="input text-sm py-1.5 px-2 h-auto w-44">
                <option value="">All types</option>
                @foreach (\App\Enums\ImportType::cases() as $t)
                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterStatus" class="input text-sm py-1.5 px-2 h-auto w-40">
                <option value="">All statuses</option>
                @foreach (\App\Enums\ImportStatus::cases() as $s)
                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                @endforeach
            </select>

            {{-- Auto-refresh indicator for in-progress --}}
            @if ($this->hasInProgressRows())
                <span wire:poll.5s class="flex items-center gap-1 text-xs text-brand dark:text-simba-400">
                    <x-ui.icon name="refresh" class="w-3 h-3 animate-spin" /> Live
                </span>
            @endif
        </div>

        <x-ui.table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>File</th>
                        <th>Status</th>
                        <th>Rows</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th>User</th>
                        <th>Date</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->history as $row)
                        <tr>
                            <td class="text-sm">{{ $row->type->label() }}</td>
                            <td class="text-sm font-mono text-xs text-slate-500 dark:text-slate-400 max-w-[160px] truncate" title="{{ $row->original_filename }}">{{ $row->original_filename }}</td>
                            <td><span class="badge badge-{{ $row->status->color() }}">{{ $row->status->label() }}</span></td>
                            <td class="text-sm tabular-nums">{{ number_format($row->total_rows) }}</td>
                            <td class="text-sm tabular-nums text-success">{{ number_format($row->created_count) }}</td>
                            <td class="text-sm tabular-nums text-slate-600 dark:text-slate-400">{{ number_format($row->updated_count) }}</td>
                            <td class="text-sm text-slate-500 dark:text-slate-400">{{ $row->uploadedBy?->name ?? '—' }}</td>
                            <td class="text-sm text-slate-500 dark:text-slate-400">{{ $row->created_at?->format('d M Y H:i') }}</td>
                            <td>
                                <a href="{{ route('admin.imports.show', $row) }}" wire:navigate class="icon-btn" title="View">
                                    <x-ui.icon name="eye" class="w-4 h-4" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state icon="archive" title="No imports yet" text="Start with step 1 above." :cols="9" />
                    @endforelse
                </tbody>
        </x-ui.table>
        <div class="p-4">{{ $this->history->links() }}</div>
    </div>

</div>
