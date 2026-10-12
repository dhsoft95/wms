<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">New User</h2>
            <p class="page-sub">Invite a new user by filling in the details below. They will receive an email to set their password.</p>
        </div>
        <a href="{{ route('admin.users.index') }}" wire:navigate class="btn btn-secondary">
            <x-ui.icon name="x" class="w-4 h-4" /> Cancel
        </a>
    </div>

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
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            wire:model="name"
                            id="name"
                            type="text"
                            placeholder="e.g. Amina Juma"
                            class="input @error('name') border-red-400 @enderror"
                            autocomplete="off"
                        />
                        @error('name')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="label" for="email">
                            Email Address <span class="text-red-500">*</span>
                        </label>
                        <input
                            wire:model="email"
                            id="email"
                            type="email"
                            placeholder="amina@example.com"
                            class="input mono @error('email') border-red-400 @enderror"
                            autocomplete="off"
                        />
                        @error('email')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
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
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

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
                                Role <span class="text-red-500">*</span>
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
                                <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Primary Department --}}
                        @if ($this->showDepartment)
                            <div wire:transition>
                                <label class="label" for="departmentId">
                                    Primary Department <span class="text-red-500">*</span>
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
                                    <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        {{-- Virtual Warehouses --}}
                        @if ($this->showVirtualWarehouses)
                            <div wire:transition>
                                <label class="label">
                                    Virtual Warehouses
                                    @if ($this->role === 'department-user')
                                        <span class="text-red-500">*</span>
                                    @endif
                                </label>

                                <p class="hint mb-2">
                                    Hold Ctrl / Cmd to select multiple.
                                </p>

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
                                    <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

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

                    <div class="card-body space-y-4">

                        {{-- Invitation notice --}}
                        <div class="flex items-start gap-3 rounded-xl bg-simba-50 dark:bg-simba-950/30 border border-simba-100 dark:border-simba-900/40 p-4">
                            <x-ui.icon name="refresh" class="w-5 h-5 text-simba-500 dark:text-simba-400 mt-0.5 flex-shrink-0" />
                            <div>
                                <p class="text-sm font-medium text-simba-800 dark:text-simba-300">Invitation-based onboarding</p>
                                <p class="text-xs text-simba-600 dark:text-simba-400 mt-0.5">
                                    No password is set here. The user will receive an email invitation with a secure link to set their own password and activate their account.
                                </p>
                            </div>
                        </div>

                        <label class="flex items-start gap-3 cursor-pointer">
                            <div class="pt-0.5">
                                <input
                                    wire:model="requireTwoFactor"
                                    type="checkbox"
                                    class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800"
                                />
                            </div>
                            <div>
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                    Require two-factor authentication
                                </span>
                                <p class="hint mt-1">
                                    The user will be prompted to set up 2FA when they first sign in.
                                </p>
                            </div>
                        </label>

                    </div>
                </div>

            </div>
        </div>


        {{-- Footer Actions --}}
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
            <a href="{{ route('admin.users.index') }}" wire:navigate class="btn btn-secondary text-center">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary justify-center">
                <x-ui.icon name="user" class="w-4 h-4" /> Send Invitation
            </button>
        </div>

    </form>

</div>
