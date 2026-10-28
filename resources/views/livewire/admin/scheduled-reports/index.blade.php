<div class="space-y-6">

    <div class="page-header">
        <div>
            <h2 class="page-title">Scheduled Reports</h2>
            <p class="page-sub">Automate report delivery on a daily, weekly, or monthly schedule.</p>
        </div>
        <button wire:click="openCreate" class="btn btn-primary">
            <x-ui.icon name="plus" class="w-4 h-4" /> New Schedule
        </button>
    </div>

    <div class="card overflow-hidden">
        @if ($schedules->isEmpty())
            <x-ui.empty-state icon="clock" title="No schedules yet"
                description="Create a schedule to automatically generate and email reports." />
        @else
            <div class="overflow-x-auto">
                <x-ui.table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Report</th>
                            <th>Frequency</th>
                            <th>Date Range</th>
                            <th>Format</th>
                            <th>Recipients</th>
                            <th>Last Run</th>
                            <th>Next Run</th>
                            <th>Active</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($schedules as $s)
                            <tr>
                                <td class="font-medium">{{ $s->name }}</td>
                                <td class="text-xs text-slate-500">{{ $s->report_key }}</td>
                                <td class="text-xs">{{ $s->frequencyLabel() }}</td>
                                <td class="text-xs">{{ \App\Models\ScheduledReport::dateRangeOptions()[$s->date_range] ?? $s->date_range }}</td>
                                <td><span class="badge badge-slate text-xs uppercase">{{ $s->format }}</span></td>
                                <td class="text-xs text-slate-500">
                                    {{ count($s->recipients) }} recipient{{ count($s->recipients) !== 1 ? 's' : '' }}
                                </td>
                                <td class="text-xs text-slate-400 tabular-nums">
                                    {{ $s->last_run_at?->diffForHumans() ?? 'Never' }}
                                </td>
                                <td class="text-xs tabular-nums {{ $s->is_active ? 'text-slate-700 dark:text-slate-300' : 'text-slate-400' }}">
                                    {{ $s->next_run_at?->format('d M Y H:i') ?? 'Not scheduled' }}
                                </td>
                                <td>
                                    <button wire:click="toggleActive({{ $s->id }})"
                                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors
                                                   {{ $s->is_active ? 'bg-simba-600' : 'bg-slate-200 dark:bg-zinc-700' }}">
                                        <span class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transition-transform
                                                     {{ $s->is_active ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                    </button>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <button wire:click="runNow({{ $s->id }})"
                                                wire:loading.attr="disabled"
                                                class="btn btn-ghost btn-xs text-green-600 hover:text-green-700 hover:bg-green-50 dark:hover:bg-green-900/20"
                                                title="Run now">
                                            <x-ui.icon name="play" class="w-3.5 h-3.5" />
                                        </button>
                                        <button wire:click="showRuns({{ $s->id }})" class="btn btn-ghost btn-xs" title="View run history">
                                            <x-ui.icon name="eye" class="w-3.5 h-3.5" />
                                        </button>
                                        <button wire:click="openEdit({{ $s->id }})" class="btn btn-ghost btn-xs" title="Edit">
                                            <x-ui.icon name="pencil" class="w-3.5 h-3.5" />
                                        </button>
                                        <button wire:click="confirmDelete({{ $s->id }})" class="btn btn-ghost btn-xs text-red-500" title="Delete">
                                            <x-ui.icon name="trash" class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </div>
        @endif
    </div>

    {{-- Create / Edit Modal --}}
    @if ($showModal)
        <x-ui.modal maxWidth="lg" title="{{ $editingId ? 'Edit Schedule' : 'New Schedule' }}"
                    wireClose="closeModal">
            <div class="space-y-5">

                <div>
                    <label class="label">Name</label>
                    <input wire:model="name" type="text" class="input w-full border border-slate-300 dark:border-slate-600"
                           placeholder="e.g. Weekly Stock Summary" maxlength="100" />
                    @error('name') <p class="hint">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="label">Report</label>
                    <select wire:model="report_key" class="input w-full text-sm border border-slate-300 dark:border-slate-600">
                        <option value="">Select a report</option>
                        @foreach ($reportDefs as $group => $defs)
                            <optgroup label="{{ $group }}">
                                @foreach ($defs as $def)
                                    <option value="{{ $def->key() }}">{{ $def->label() }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('report_key') <p class="hint">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="label">Frequency</label>
                        <select wire:model.live="frequency" class="input w-full text-sm border border-slate-300 dark:border-slate-600">
                            @foreach (\App\Models\ScheduledReport::frequencyOptions() as $val => $lbl)
                                <option value="{{ $val }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($frequency === 'weekly')
                        <div>
                            <label class="label">Day of Week</label>
                            <select wire:model="send_day" class="input w-full text-sm border border-slate-300 dark:border-slate-600">
                                @foreach (['', 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $i => $day)
                                    @if ($i > 0)
                                        <option value="{{ $i }}">{{ $day }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    @elseif ($frequency === 'monthly')
                        <div>
                            <label class="label">Day of Month</label>
                            <select wire:model="send_day" class="input w-full text-sm border border-slate-300 dark:border-slate-600">
                                @foreach (range(1, 28) as $d)
                                    <option value="{{ $d }}">{{ $d }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div></div>
                    @endif

                    <div>
                        <label class="label">Send Hour</label>
                        <select wire:model="send_hour" class="input w-full text-sm border border-slate-300 dark:border-slate-600">
                            @foreach (range(0, 23) as $h)
                                <option value="{{ $h }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}:00</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label">Date Range</label>
                        <select wire:model="date_range" class="input w-full text-sm border border-slate-300 dark:border-slate-600">
                            @foreach (\App\Models\ScheduledReport::dateRangeOptions() as $val => $lbl)
                                <option value="{{ $val }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Format</label>
                        <select wire:model="format" class="input w-full text-sm border border-slate-300 dark:border-slate-600">
                            <option value="excel">Excel (.xlsx)</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label">Warehouse</label>
                        <select wire:model="warehouse_id" class="input w-full text-sm border border-slate-300 dark:border-slate-600">
                            <option value="">All warehouses</option>
                            @foreach ($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Department</label>
                        <select wire:model="department_id" class="input w-full text-sm border border-slate-300 dark:border-slate-600">
                            <option value="">All departments</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="label">Recipients</label>
                    <textarea wire:model="recipients_text" rows="3"
                              class="input w-full font-mono text-xs border border-slate-300 dark:border-slate-600"
                              placeholder="Enter email addresses, one per line or comma-separated"></textarea>
                    <p class="hint mt-1">The report will be emailed to these addresses as an attachment.</p>
                    @error('recipients_text') <p class="hint">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" wire:click="$toggle('is_active')"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors
                                   {{ $is_active ? 'bg-simba-600' : 'bg-slate-200 dark:bg-zinc-700' }}">
                        <span class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transition-transform
                                     {{ $is_active ? 'translate-x-4' : 'translate-x-0' }}"></span>
                    </button>
                    <span class="text-sm text-slate-700 dark:text-slate-300">Active</span>
                </div>

            </div>

            <x-slot:footer>
                <button wire:click="closeModal" class="btn btn-ghost">Cancel</button>
                <button wire:click="save" wire:loading.attr="disabled" class="btn btn-primary">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save Changes' : 'Create Schedule' }}</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Run History Modal --}}
    @if ($viewId)
        <x-ui.modal maxWidth="xl" title="Run History: {{ $viewScheduleName }}" wireClose="closeRunsModal">
            @if ($recentRuns->isEmpty())
                <p class="text-sm text-slate-500 py-4 text-center">No runs yet for this schedule.</p>
            @else
                <x-ui.table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Period</th>
                            <th>Format</th>
                            <th>Status</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentRuns as $run)
                            <tr>
                                <td class="text-xs tabular-nums text-slate-500">{{ $run->created_at->format('d M Y H:i') }}</td>
                                <td class="text-xs text-slate-500">
                                    @if ($run->date_from && $run->date_to)
                                        {{ $run->date_from->format('d M Y') }} to {{ $run->date_to->format('d M Y') }}
                                    @else
                                        All dates
                                    @endif
                                </td>
                                <td><span class="badge badge-slate text-xs uppercase">{{ $run->format }}</span></td>
                                <td>
                                    @php
                                        $statusClass = match($run->status) {
                                            'ready'      => 'badge-green',
                                            'failed'     => 'badge-red',
                                            'processing' => 'badge-yellow',
                                            default      => 'badge-slate',
                                        };
                                    @endphp
                                    <span class="badge {{ $statusClass }} text-xs capitalize">{{ $run->status }}</span>
                                </td>
                                <td class="text-xs text-red-500 max-w-xs truncate" title="{{ $run->error }}">
                                    {{ $run->error ?? '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif

            <x-slot:footer>
                <button wire:click="closeRunsModal" class="btn btn-ghost">Close</button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- Delete Confirm --}}
    @if ($deleteId)
        <x-ui.modal maxWidth="sm" title="Delete Schedule" wireClose="closeDeleteModal">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Delete <strong>{{ $deleteConfirmName }}</strong>? This cannot be undone.
            </p>
            <x-slot:footer>
                <button wire:click="closeDeleteModal" class="btn btn-ghost">Cancel</button>
                <button wire:click="delete" class="btn btn-danger">Delete</button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

</div>
