<form wire:submit="save" class="w-full space-y-6">

    {{-- Two Column Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

        {{-- LEFT: Identity --}}
        <div class="card h-full">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="user" class="w-4 h-4" />
                    Identity
                </h3>
            </div>

            <div class="card-body space-y-5">

                <div>
                    <label class="label" for="name">
                        Full Name <span class="required-mark">*</span>
                    </label>
                    <input
                        wire:model="name"
                        id="name"
                        type="text"
                        placeholder="e.g. Amina Juma"
                        class="input @error('name') border-red-400 @enderror"
                    />
                    @error('name')
                        <p class="hint hint-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="label" for="email">
                        Email Address <span class="required-mark">*</span>
                    </label>
                    <input
                        wire:model="email"
                        id="email"
                        type="email"
                        placeholder="amina@example.com"
                        class="input mono @error('email') border-red-400 @enderror"
                    />
                    @if ($user->email_verified_at)
                        <p class="hint text-emerald-600 dark:text-emerald-400">
                            Verified {{ $user->email_verified_at->diffForHumans() }}
                        </p>
                    @else
                        <p class="hint text-amber-600 dark:text-amber-400">Email not verified</p>
                    @endif
                    @error('email')
                        <p class="hint hint-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="label" for="phone">
                        Phone <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span>
                    </label>
                    <input
                        wire:model="phone"
                        id="phone"
                        type="tel"
                        placeholder="+255 7XX XXX XXX"
                        class="input @error('phone') border-red-400 @enderror"
                    />
                    @error('phone')
                        <p class="hint hint-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Invited By --}}
                @if ($user->invitedBy)
                    <div>
                        <label class="label">Invited By</label>
                        <div class="flex items-center gap-3 py-3 px-3 rounded-xl
                                    bg-white/40 dark:bg-white/[0.06]
                                    border border-white/50 dark:border-white/[0.08]">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0
                                        bg-simba-50 dark:bg-simba-500/20
                                        text-xs font-semibold text-simba-700 dark:text-simba-300 mono">
                                {{ $user->invitedBy->initials() }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                    {{ $user->invitedBy->name }}
                                </p>
                                <p class="text-xs text-slate-400 dark:text-slate-500">
                                    Invited {{ $user->invitation_sent_at?->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>


        {{-- RIGHT: Access & Security --}}
        <div class="space-y-6">

            {{-- Access & Role --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <x-ui.icon name="shield" class="w-4 h-4" />
                        Access & Role
                    </h3>
                </div>

                <div class="card-body space-y-5">

                    <div>
                        <label class="label" for="role">
                            Role <span class="required-mark">*</span>
                        </label>
                        <select
                            wire:model.live="role"
                            id="role"
                            class="input @error('role') border-red-400 @enderror"
                        >
                            <option value="">Select a role…</option>
                            <option value="system-admin">System Admin</option>
                            <option value="warehouse-manager">Warehouse Manager</option>
                            <option value="department-user">Department User</option>
                            <option value="storekeeper">Storekeeper</option>
                            <option value="scanner-operator">Scanner Operator</option>
                            <option value="viewer-auditor">Viewer / Auditor</option>
                        </select>
                        @error('role')
                            <p class="hint hint-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Primary Department --}}
                    @if ($this->showDepartment)
                        <div wire:transition>
                            <label class="label" for="departmentId">
                                Primary Department <span class="required-mark">*</span>
                            </label>
                            <select
                                wire:model="departmentId"
                                id="departmentId"
                                class="input @error('departmentId') border-red-400 @enderror"
                            >
                                <option value="">Select a department…</option>
                                @foreach ($this->departments as $dept)
                                    <option value="{{ $dept->id }}">
                                        {{ $dept->name }} ({{ $dept->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('departmentId')
                                <p class="hint hint-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    {{-- Virtual Warehouses --}}
                    @if ($this->showVirtualWarehouses)
                        <div wire:transition>
                            <label class="label">
                                Virtual Warehouses
                                @if ($this->role === 'department-user')
                                    <span class="required-mark">*</span>
                                @endif
                            </label>
                            <p class="hint mb-2">Hold Ctrl / Cmd to select multiple.</p>
                            <select
                                wire:model="virtualWarehouseIds"
                                multiple
                                class="input min-h-[120px] @error('virtualWarehouseIds.*') border-red-400 @enderror"
                            >
                                @foreach ($this->availableVirtualWarehouses as $vw)
                                    <option value="{{ $vw->id }}">
                                        {{ strtolower($vw->name) }} ({{ $vw->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('virtualWarehouseIds.*')
                                <p class="hint hint-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    {{-- Account Status --}}
                    <div>
                        <label class="label" for="status">
                            Account Status <span class="required-mark">*</span>
                        </label>
                        <select
                            wire:model="status"
                            id="status"
                            class="input @error('status') border-red-400 @enderror"
                        >
                            <option value="invited">Invited</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                        </select>
                        @error('status')
                            <p class="hint hint-error">{{ $message }}</p>
                        @enderror
                        <p class="hint mt-1">
                            Changing to <strong>Suspended</strong> will immediately block login access.
                        </p>
                    </div>

                </div>
            </div>


            {{-- Security --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <x-ui.icon name="shield-check" class="w-4 h-4" />
                        Security
                    </h3>
                </div>

                <div class="card-body">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <div class="pt-0.5">
                            <input
                                wire:model="requireTwoFactor"
                                type="checkbox"
                                class="rounded border-slate-300 dark:border-slate-600
                                       text-simba-600 focus:ring-simba-600 dark:bg-zinc-800"
                            />
                        </div>
                        <div>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                Require two-factor authentication
                            </span>
                            <p class="hint mt-1">
                                The user will be prompted to set up 2FA when they next sign in.
                            </p>
                        </div>
                    </label>
                </div>
            </div>

        </div>
    </div>


    {{-- Footer Actions --}}
    <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 pt-4
                border-t border-white/40 dark:border-white/[0.08]">
        <a
            href="{{ route('admin.users.show', $user) }}"
            wire:navigate
            class="btn btn-secondary text-center"
        >
            Cancel
        </a>
        <button type="submit" class="btn btn-primary justify-center">
            <x-ui.icon name="check" class="w-4 h-4" />
            Save Changes
        </button>
    </div>

</form>
