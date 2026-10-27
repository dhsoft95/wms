<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Settings</h2>
            <p class="page-sub">Configure company profile, operational rules, and reference data.</p>
        </div>
    </div>

    {{-- Tab Navigation --}}
    <div class="border-b border-white/40 dark:border-white/[0.08]">
        <nav class="-mb-px flex gap-1 overflow-x-auto">

            @php
                $tabs = [
                    'company'       => 'Company Profile',
                    'operations'    => 'Operations',
                    'sequences'     => 'Document Sequences',
                    'reason-codes'  => 'Reason Codes',
                    'uom'           => 'Units of Measure',
                    'outbound'      => 'Approval',
                    'vehicle-types' => 'Vehicle Types',
                ];
            @endphp

            @foreach ($tabs as $key => $label)
                <button
                    wire:click="$set('activeTab', '{{ $key }}')"
                    class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition-colors
                           {{ $activeTab === $key
                               ? 'border-simba-500 text-simba-600 dark:text-simba-400'
                               : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:hover:text-slate-300' }}"
                >
                    {{ $label }}
                </button>
            @endforeach

        </nav>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- Tab: Company Profile                                                    --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}

    @if ($activeTab === 'company')
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Company Profile</h3>
            </div>

            <form wire:submit="saveCompany" class="card-body space-y-5">

                {{-- Company Name --}}
                <div>
                    <label for="companyName" class="label">Company Name <span class="required-mark">*</span></label>
                    <input
                        id="companyName"
                        wire:model="companyName"
                        type="text"
                        class="input w-full"
                        placeholder="Acme Logistics Ltd."
                    />
                    @error('companyName') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>

                {{-- Email & Phone --}}
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="companyEmail" class="label">Email Address</label>
                        <input
                            id="companyEmail"
                            wire:model="companyEmail"
                            type="email"
                            class="input w-full"
                            placeholder="info@company.com"
                        />
                        @error('companyEmail') <p class="hint hint-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="companyPhone" class="label">Phone Number</label>
                        <input
                            id="companyPhone"
                            wire:model="companyPhone"
                            type="text"
                            class="input w-full"
                            placeholder="+255 700 000 000"
                        />
                        @error('companyPhone') <p class="hint hint-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- TIN --}}
                <div>
                    <label for="companyTin" class="label">TIN (Tax Identification Number)</label>
                    <input
                        id="companyTin"
                        wire:model="companyTin"
                        type="text"
                        class="input w-full sm:max-w-xs"
                        placeholder="000-000-000"
                    />
                    @error('companyTin') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>

                {{-- Address --}}
                <div>
                    <label for="companyAddress" class="label">Physical Address</label>
                    <textarea
                        id="companyAddress"
                        wire:model="companyAddress"
                        rows="3"
                        class="input w-full"
                        placeholder="P.O. Box 1234, Dar es Salaam, Tanzania"
                    ></textarea>
                    @error('companyAddress') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>

                {{-- Logo Upload --}}
                <div>
                    <label class="label">Company Logo</label>

                    @php $currentLogo = \App\Services\Foundation\SettingsService::get('company.logo_path'); @endphp

                    @if ($currentLogo)
                        <div class="mb-3">
                            <img
                                src="{{ Storage::url($currentLogo) }}"
                                alt="Current logo"
                                class="h-14 w-auto rounded-lg object-contain
                                       ring-1 ring-white/60 dark:ring-white/[0.12]
                                       bg-white/50 dark:bg-white/[0.05] p-1"
                            />
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Current logo — upload a new file to replace it.</p>
                        </div>
                    @endif

                    @if ($logo)
                        <div class="mb-3">
                            <img
                                src="{{ $logo->temporaryUrl() }}"
                                alt="Preview"
                                class="h-14 w-auto rounded-lg object-contain
                                       ring-1 ring-simba-400/60 dark:ring-simba-500/40
                                       bg-white/50 dark:bg-white/[0.05] p-1"
                            />
                            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Preview — not yet saved.</p>
                        </div>
                    @endif

                    <input
                        wire:model="logo"
                        type="file"
                        accept="image/*"
                        class="block w-full text-sm text-slate-500 dark:text-slate-400
                               file:mr-4 file:rounded-lg file:border-0 file:py-2 file:px-3
                               file:text-sm file:font-medium
                               file:bg-white/60 file:text-slate-700
                               dark:file:bg-white/[0.08] dark:file:text-slate-300
                               hover:file:bg-white/80 dark:hover:file:bg-white/[0.12]
                               transition"
                    />
                    <p class="hint">PNG or SVG recommended. Max 2 MB.</p>
                    @error('logo') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>

                {{-- Save --}}
                <div class="flex justify-end border-t border-white/30 dark:border-white/[0.06] pt-4">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveCompany">Save Company Profile</span>
                        <span wire:loading wire:target="saveCompany">Saving…</span>
                    </button>
                </div>

            </form>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- Tab: Operations                                                          --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}

    @if ($activeTab === 'operations')
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Operations</h3>
            </div>

            <form wire:submit="saveOperations" class="card-body space-y-5">

                {{-- Global Fallback Allocation Rule --}}
                <div>
                    <label for="allocationRule" class="label">Global Allocation Rule (Fallback)</label>
                    <select id="allocationRule" wire:model="allocationRule" class="input w-full sm:max-w-xs">
                        <option value="fifo">FIFO — First In, First Out</option>
                        <option value="fefo">FEFO — First Expiry, First Out</option>
                        <option value="lifo">LIFO — Last In, First Out</option>
                    </select>
                    <p class="hint">Used for items that have no explicit allocation rules set. Per-item rules take priority.</p>
                    @error('allocationRule') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>

                <p class="hint">Variance and adjustment approval thresholds are now configured per item on the Items master data page.</p>

                {{-- Save --}}
                <div class="flex justify-end border-t border-white/30 dark:border-white/[0.06] pt-4">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveOperations">Save Operations Settings</span>
                        <span wire:loading wire:target="saveOperations">Saving…</span>
                    </button>
                </div>

            </form>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- Tab: Document Sequences                                                  --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}

    @if ($activeTab === 'sequences')
        <div class="card overflow-hidden">
            <div class="card-header">
                <h3 class="card-title">Document Sequences</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Configure how each document number is generated. The next number is read-only.
                </p>
            </div>

            <x-ui.table class="min-w-[780px]">

                    <thead class="text-[11px] font-semibold uppercase tracking-wider
                                  text-slate-500 dark:text-slate-400
                                  bg-white/[0.45] dark:bg-white/[0.04]
                                  border-b border-white/50 dark:border-white/[0.07]">
                        <tr>
                            <th class="px-5 py-3">Document Type</th>
                            <th class="px-5 py-3">Prefix</th>
                            <th class="px-5 py-3">Padding</th>
                            <th class="px-5 py-3 text-center">Include Year</th>
                            <th class="px-5 py-3 text-center">Reset Yearly</th>
                            <th class="px-5 py-3">Next #</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
                    @forelse ($this->sequences as $seq)
                        <tr wire:key="seq-{{ $seq->id }}"
                            class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">

                            {{-- Type --}}
                            <td class="px-5 py-4">
                                <span class="font-medium text-slate-800 dark:text-slate-200">
                                    {{ $seq->type instanceof \App\Enums\DocumentSequenceType ? $seq->type->label() : $seq->type }}
                                </span>
                                <span class="ml-1.5 rounded bg-slate-100 dark:bg-white/[0.07] px-1.5 py-0.5 text-[11px] font-mono text-slate-500 dark:text-slate-400">
                                    {{ $seq->type instanceof \App\Enums\DocumentSequenceType ? $seq->type->value : $seq->type }}
                                </span>
                            </td>

                            {{-- Prefix --}}
                            <td class="px-5 py-4">
                                <input
                                    wire:model="sequenceData.{{ $seq->id }}.prefix"
                                    type="text"
                                    class="input w-24 font-mono text-sm"
                                    placeholder="{{ $seq->type instanceof \App\Enums\DocumentSequenceType ? $seq->type->value : $seq->type }}"
                                />
                            </td>

                            {{-- Padding --}}
                            <td class="px-5 py-4">
                                <input
                                    wire:model="sequenceData.{{ $seq->id }}.padding"
                                    type="number"
                                    min="1"
                                    max="10"
                                    class="input w-20 text-sm"
                                />
                            </td>

                            {{-- Include Year --}}
                            <td class="px-5 py-4 text-center">
                                <input
                                    wire:model="sequenceData.{{ $seq->id }}.include_year"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 dark:border-slate-600
                                           text-simba-600 focus:ring-simba-500"
                                />
                            </td>

                            {{-- Reset Yearly --}}
                            <td class="px-5 py-4 text-center">
                                <input
                                    wire:model="sequenceData.{{ $seq->id }}.reset_yearly"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 dark:border-slate-600
                                           text-simba-600 focus:ring-simba-500"
                                />
                            </td>

                            {{-- Next Number --}}
                            <td class="px-5 py-4">
                                <span class="rounded-md bg-slate-100 dark:bg-white/[0.07]
                                             px-2.5 py-1 font-mono text-sm
                                             text-slate-600 dark:text-slate-300">
                                    {{ str_pad($seq->next_number, $seq->padding, '0', STR_PAD_LEFT) }}
                                </span>
                            </td>

                            {{-- Save --}}
                            <td class="px-5 py-4 text-right">
                                <button
                                    wire:click="saveSequence({{ $seq->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="saveSequence({{ $seq->id }})"
                                    class="btn btn-primary text-xs py-1.5 px-3"
                                >
                                    <span wire:loading.remove wire:target="saveSequence({{ $seq->id }})">Save</span>
                                    <span wire:loading wire:target="saveSequence({{ $seq->id }})">…</span>
                                </button>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-14 text-center">
                                <p class="font-medium text-slate-900 dark:text-white">No sequences configured</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Run the database seeder to initialise document sequences.</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>

                </x-ui.table>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- Tab: Reason Codes                                                        --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}

    @if ($activeTab === 'reason-codes')
        <div class="space-y-4">

            {{-- Table card --}}
            <div class="card overflow-hidden">
                <div class="flex flex-col gap-3 p-4 border-b border-white/40 dark:border-white/[0.08]
                            sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="card-title">Reason Codes</h3>
                    <div class="relative sm:w-64">
                        <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            wire:model.live.debounce.300ms="reasonCodeSearch"
                            type="search"
                            placeholder="Search code or name…"
                            class="input w-full pl-9"
                        />
                    </div>
                </div>

                <div wire:loading.class="opacity-50" wire:target="reasonCodeSearch">
                    <x-ui.table>

                        <thead class="text-[11px] font-semibold uppercase tracking-wider
                                      text-slate-500 dark:text-slate-400
                                      bg-white/[0.45] dark:bg-white/[0.04]
                                      border-b border-white/50 dark:border-white/[0.07]">
                            <tr>
                                <th class="px-5 py-3">Code</th>
                                <th class="px-5 py-3">Name</th>
                                <th class="px-5 py-3">Applies To</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
                        @forelse ($this->reasonCodes as $rc)
                            <tr wire:key="rc-{{ $rc->id }}"
                                class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]
                                       {{ $rcEditing === $rc->id ? 'bg-simba-50/40 dark:bg-simba-900/10' : '' }}">

                                <td class="px-5 py-3.5">
                                    <span class="rounded bg-slate-100 dark:bg-white/[0.07] px-2 py-0.5 font-mono text-xs
                                                 text-slate-700 dark:text-slate-300">
                                        {{ $rc->code }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-slate-700 dark:text-slate-200">
                                    {{ $rc->name }}
                                </td>
                                <td class="px-5 py-3.5 text-slate-500 dark:text-slate-400">
                                    {{ $rc->applies_to instanceof \App\Enums\ReasonCodeAppliesTo ? $rc->applies_to->label() : $rc->applies_to }}
                                </td>
                                <td class="px-5 py-3.5">
                                    @if ($rc->is_active)
                                        <span class="badge badge-green">Active</span>
                                    @else
                                        <span class="badge badge-red">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <x-ui.row-actions :label="'Actions for ' . $rc->name">
                                        <x-ui.row-actions.item icon="pencil" label="Edit" wire:click="editReasonCode({{ $rc->id }})" />
                                        <x-ui.row-actions.divider />
                                        <x-ui.row-actions.item icon="trash" label="Delete" tone="danger" wire:confirm="Delete {{ $rc->name }}?" wire:click="deleteReasonCode({{ $rc->id }})" />
                                    </x-ui.row-actions>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-14 text-center">
                                    <p class="font-medium text-slate-900 dark:text-white">No reason codes found</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        @if ($reasonCodeSearch)
                                            Try a different search term.
                                        @else
                                            Use the form below to add the first reason code.
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>

                    </x-ui.table>
                </div>
            </div>

            {{-- Create / Edit form --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        {{ $rcEditing ? 'Edit Reason Code' : 'New Reason Code' }}
                    </h3>
                </div>

                <form wire:submit="saveReasonCode" class="card-body space-y-4">

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {{-- Code --}}
                        <div>
                            <label for="rcCode" class="label">Code <span class="required-mark">*</span></label>
                            <input
                                id="rcCode"
                                wire:model="rcCode"
                                type="text"
                                class="input w-full font-mono"
                                placeholder="e.g. VAR-DMGD"
                                maxlength="20"
                            />
                            @error('rcCode') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Name --}}
                        <div>
                            <label for="rcName" class="label">Name <span class="required-mark">*</span></label>
                            <input
                                id="rcName"
                                wire:model="rcName"
                                type="text"
                                class="input w-full"
                                placeholder="e.g. Damaged goods variance"
                                maxlength="255"
                            />
                            @error('rcName') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {{-- Applies To --}}
                        <div>
                            <label for="rcAppliesTo" class="label">Applies To <span class="required-mark">*</span></label>
                            <select id="rcAppliesTo" wire:model="rcAppliesTo" class="input w-full">
                                <option value="">Select…</option>
                                <option value="variance">Variance</option>
                                <option value="adjustment">Adjustment</option>
                                <option value="short_pick">Short Pick</option>
                                <option value="reprint">Reprint</option>
                                <option value="hold">Hold</option>
                                <option value="seal_mismatch">Seal Mismatch</option>
                            </select>
                            @error('rcAppliesTo') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Is Active --}}
                        <div class="flex items-center gap-3 pt-6">
                            <input
                                id="rcIsActive"
                                wire:model="rcIsActive"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 dark:border-slate-600
                                       text-simba-600 focus:ring-simba-500"
                            />
                            <label for="rcIsActive" class="label !mb-0 cursor-pointer">Active</label>
                            @error('rcIsActive') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-white/30 dark:border-white/[0.06] pt-4">
                        @if ($rcEditing)
                            <button type="button" wire:click="cancelRcEdit" class="btn">Cancel</button>
                        @endif
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveReasonCode">
                            <span wire:loading.remove wire:target="saveReasonCode">
                                {{ $rcEditing ? 'Update Reason Code' : 'Create Reason Code' }}
                            </span>
                            <span wire:loading wire:target="saveReasonCode">Saving…</span>
                        </button>
                    </div>

                </form>
            </div>

        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- Tab: Units of Measure                                                    --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}

    @if ($activeTab === 'uom')
        <div class="space-y-4">

            {{-- Table card --}}
            <div class="card overflow-hidden">
                <div class="flex flex-col gap-3 p-4 border-b border-white/40 dark:border-white/[0.08]
                            sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="card-title">Units of Measure</h3>
                    <div class="relative sm:w-64">
                        <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            wire:model.live.debounce.300ms="uomSearch"
                            type="search"
                            placeholder="Search code or name…"
                            class="input w-full pl-9"
                        />
                    </div>
                </div>

                <div wire:loading.class="opacity-50" wire:target="uomSearch">
                    <x-ui.table>

                        <thead class="text-[11px] font-semibold uppercase tracking-wider
                                      text-slate-500 dark:text-slate-400
                                      bg-white/[0.45] dark:bg-white/[0.04]
                                      border-b border-white/50 dark:border-white/[0.07]">
                            <tr>
                                <th class="px-5 py-3">Code</th>
                                <th class="px-5 py-3">Name</th>
                                <th class="px-5 py-3">Type</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-white/50 dark:divide-white/[0.06]">
                        @forelse ($this->unitOfMeasures as $uom)
                            <tr wire:key="uom-{{ $uom->id }}"
                                class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]
                                       {{ $uomEditing === $uom->id ? 'bg-simba-50/40 dark:bg-simba-900/10' : '' }}">

                                <td class="px-5 py-3.5">
                                    <span class="rounded bg-slate-100 dark:bg-white/[0.07] px-2 py-0.5 font-mono text-xs
                                                 text-slate-700 dark:text-slate-300">
                                        {{ $uom->code }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-slate-700 dark:text-slate-200">
                                    {{ $uom->name }}
                                </td>
                                <td class="px-5 py-3.5 text-slate-500 dark:text-slate-400 capitalize">
                                    {{ $uom->type instanceof \App\Enums\UomType ? $uom->type->label() : $uom->type }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <x-ui.row-actions :label="'Actions for ' . $uom->name">
                                        <x-ui.row-actions.item icon="pencil" label="Edit" wire:click="editUom({{ $uom->id }})" />
                                        <x-ui.row-actions.divider />
                                        <x-ui.row-actions.item icon="trash" label="Delete" tone="danger" wire:confirm="Delete {{ $uom->name }}?" wire:click="deleteUom({{ $uom->id }})" />
                                    </x-ui.row-actions>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-14 text-center">
                                    <p class="font-medium text-slate-900 dark:text-white">No units of measure found</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        @if ($uomSearch)
                                            Try a different search term.
                                        @else
                                            Use the form below to add the first unit.
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>

                    </x-ui.table>
                </div>
            </div>

            {{-- Create / Edit form --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        {{ $uomEditing ? 'Edit Unit of Measure' : 'New Unit of Measure' }}
                    </h3>
                </div>

                <form wire:submit="saveUom" class="card-body space-y-4">

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        {{-- Code --}}
                        <div>
                            <label for="uomCode" class="label">Code <span class="required-mark">*</span></label>
                            <input
                                id="uomCode"
                                wire:model="uomCode"
                                type="text"
                                class="input w-full font-mono"
                                placeholder="e.g. KG"
                                maxlength="20"
                            />
                            @error('uomCode') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Name --}}
                        <div>
                            <label for="uomName" class="label">Name <span class="required-mark">*</span></label>
                            <input
                                id="uomName"
                                wire:model="uomName"
                                type="text"
                                class="input w-full"
                                placeholder="e.g. Kilogram"
                                maxlength="100"
                            />
                            @error('uomName') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>

                        {{-- Type --}}
                        <div>
                            <label for="uomType" class="label">Type <span class="required-mark">*</span></label>
                            <select id="uomType" wire:model="uomType" class="input w-full">
                                <option value="">Select…</option>
                                <option value="count">Count</option>
                                <option value="weight">Weight</option>
                                <option value="volume">Volume</option>
                                <option value="length">Length</option>
                            </select>
                            @error('uomType') <p class="hint hint-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-white/30 dark:border-white/[0.06] pt-4">
                        @if ($uomEditing)
                            <button type="button" wire:click="cancelUomEdit" class="btn">Cancel</button>
                        @endif
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveUom">
                            <span wire:loading.remove wire:target="saveUom">
                                {{ $uomEditing ? 'Update Unit' : 'Create Unit' }}
                            </span>
                            <span wire:loading wire:target="saveUom">Saving…</span>
                        </button>
                    </div>

                </form>
            </div>

        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- Tab: Vehicle Types                                                        --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}

    @if ($activeTab === 'vehicle-types')
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Vehicle Types</h3>
            </div>

            <form wire:submit="saveVehicleTypes" class="card-body space-y-5">

                <div>
                    <label for="vehicleTypes" class="label">Vehicle Types</label>
                    <textarea
                        id="vehicleTypes"
                        wire:model="vehicleTypes"
                        rows="5"
                        class="input w-full font-mono text-sm"
                        placeholder="Truck,Van,Pickup,Trailer,Other"
                    ></textarea>
                    <p class="hint">Enter vehicle types as a comma-separated list. These are used when assigning dispatch vehicles to delivery orders.</p>
                    @error('vehicleTypes') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>

                {{-- Preview --}}
                @if ($vehicleTypes)
                    <div>
                        <p class="label mb-2">Preview</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach (array_filter(array_map('trim', explode(',', $vehicleTypes))) as $vt)
                                <span class="badge badge-slate">{{ $vt }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Save --}}
                <div class="flex justify-end border-t border-white/30 dark:border-white/[0.06] pt-4">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveVehicleTypes">Save Vehicle Types</span>
                        <span wire:loading wire:target="saveVehicleTypes">Saving…</span>
                    </button>
                </div>

            </form>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- Tab: Approval (formerly Outbound)                                         --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}

    @if ($activeTab === 'outbound')
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Approval Settings</h3>
            </div>

            <form wire:submit="saveOutbound" class="card-body space-y-5">

                <p class="hint">Dispatch and adjustment approval rules are now configured per item. Configure outbound allocation behaviour below.</p>

                {{-- Partial Allocation + Auto Backorder --}}
                <fieldset class="rounded-xl border border-white/30 dark:border-white/[0.08] p-4 space-y-4">
                    <legend class="px-2 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Partial Allocation
                    </legend>

                    <div class="flex items-center gap-3">
                        <input
                            id="outboundAllowPartial"
                            wire:model.live="outboundAllowPartial"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-500"
                        />
                        <label for="outboundAllowPartial" class="label !mb-0 cursor-pointer">Allow partial allocation</label>
                    </div>
                    <p class="hint -mt-2">When enabled, a delivery order can be allocated with less stock than requested.</p>

                    @if ($outboundAllowPartial)
                        <div class="flex items-center gap-3 pl-7">
                            <input
                                id="outboundAutoBackorder"
                                wire:model="outboundAutoBackorder"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-500"
                            />
                            <label for="outboundAutoBackorder" class="label !mb-0 cursor-pointer">Auto-create backorder for the remainder</label>
                        </div>
                        <p class="hint -mt-2 pl-7">A new delivery order is created automatically for any unallocated quantity.</p>
                    @endif
                </fieldset>

                {{-- Min Shelf Life --}}
                <div>
                    <label for="outboundMinShelfLifeDays" class="label">Minimum Remaining Shelf Life (days)</label>
                    <input
                        id="outboundMinShelfLifeDays"
                        wire:model="outboundMinShelfLifeDays"
                        type="number"
                        min="0"
                        max="365"
                        class="input w-full sm:max-w-xs"
                        placeholder="0"
                    />
                    <p class="hint">Stock expiring within this many days will not be allocated. Set to 0 to disable.</p>
                    @error('outboundMinShelfLifeDays') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>

                {{-- Short Pick Behaviour --}}
                <div>
                    <label for="outboundShortPickBehaviour" class="label">Short Pick Behaviour</label>
                    <select id="outboundShortPickBehaviour" wire:model="outboundShortPickBehaviour" class="input w-full sm:max-w-xs">
                        <option value="leave_short">Leave short — mark task as short and notify managers</option>
                        <option value="reallocate">Reallocate — try other bins automatically for the missing qty</option>
                    </select>
                    <p class="hint">What happens when a picker cannot find the full quantity at the assigned bin.</p>
                    @error('outboundShortPickBehaviour') <p class="hint hint-error">{{ $message }}</p> @enderror
                </div>

                {{-- Save --}}
                <div class="flex justify-end border-t border-white/30 dark:border-white/[0.06] pt-4">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveOutbound">Save Outbound Settings</span>
                        <span wire:loading wire:target="saveOutbound">Saving…</span>
                    </button>
                </div>

            </form>
        </div>
    @endif

</div>
