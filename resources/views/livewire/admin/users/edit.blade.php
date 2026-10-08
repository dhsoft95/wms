<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Edit User</h2>
            <p class="page-sub">Update details and permissions for {{ $user->name }}.</p>
        </div>
        <a href="{{ route('admin.users.show', $user) }}" wire:navigate class="btn btn-secondary">
            <x-ui.icon name="x" class="w-4 h-4" /> Cancel
        </a>
    </div>

    <form wire:submit="save" class="max-w-2xl space-y-6">

        {{-- Identity --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="user" class="w-4 h-4" /> Identity
                </h3>
            </div>
            <div class="card-body space-y-4">
                <div>
                    <label class="label" for="name">Full Name <span class="text-red-500">*</span></label>
                    <input
                        wire:model="name"
                        id="name"
                        type="text"
                        placeholder="e.g. Amina Juma"
                        class="input @error('name') border-red-400 @enderror"
                    />
                    @error('name')
                        <p class="hint !text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="label" for="email">Email Address <span class="text-red-500">*</span></label>
                    <input
                        wire:model="email"
                        id="email"
                        type="email"
                        placeholder="amina@example.com"
                        class="input mono @error('email') border-red-400 @enderror"
                    />
                    @if ($user->email_verified_at)
                        <p class="hint text-green-600">Verified {{ $user->email_verified_at->diffForHumans() }}</p>
                    @else
                        <p class="hint text-amber-600">Email not verified</p>
                    @endif
                    @error('email')
                        <p class="hint !text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="label" for="phone">Phone <span class="text-slate-400 font-normal">(optional)</span></label>
                    <input
                        wire:model="phone"
                        id="phone"
                        type="tel"
                        placeholder="+255 7XX XXX XXX"
                        class="input @error('phone') border-red-400 @enderror"
                    />
                    @error('phone')
                        <p class="hint !text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Invited By (read-only) --}}
                @if ($user->invitedBy)
                    <div>
                        <label class="label">Invited By</label>
                        <div class="flex items-center gap-2 py-2 px-3 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <div class="w-6 h-6 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-semibold text-slate-500 mono">{{ $user->invitedBy->initials() }}</span>
                            </div>
                            <span class="text-sm text-slate-700 dark:text-slate-300">{{ $user->invitedBy->name }}</span>
                            <span class="text-xs text-slate-400 ml-auto">{{ $user->invitation_sent_at?->diffForHumans() }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Access & Role --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="shield" class="w-4 h-4" /> Access & Role
                </h3>
            </div>
            <div class="card-body space-y-4">
                <div>
                    <label class="label" for="role">Role <span class="text-red-500">*</span></label>
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
                        <p class="hint !text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Department — only when role is department-user --}}
                @if ($this->showDepartment)
                    <div wire:transition>
                        <label class="label" for="departmentId">Department <span class="text-red-500">*</span></label>
                        <select
                            wire:model="departmentId"
                            id="departmentId"
                            class="input @error('departmentId') border-red-400 @enderror"
                        >
                            <option value="">Select a department…</option>
                            @foreach ($this->departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }} ({{ $dept->code }})</option>
                            @endforeach
                        </select>
                        @error('departmentId')
                            <p class="hint !text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div>
                    <label class="label" for="status">Account Status <span class="text-red-500">*</span></label>
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
                        <p class="hint !text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="hint">Changing to <strong>Suspended</strong> will immediately block login access.</p>
                </div>
            </div>
        </div>

        {{-- Security --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="shield" class="w-4 h-4" /> Security
                </h3>
            </div>
            <div class="card-body">
                <label class="flex items-start gap-3 cursor-pointer">
                    <div class="pt-0.5">
                        <input
                            wire:model="requireTwoFactor"
                            type="checkbox"
                            class="rounded border-slate-300 text-simba-600 focus:ring-simba-600"
                        />
                    </div>
                    <div>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Require two-factor authentication</span>
                        <p class="hint">The user will be prompted to set up 2FA when they next sign in.</p>
                    </div>
                </label>
            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('admin.users.show', $user) }}" wire:navigate class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <x-ui.icon name="check" class="w-4 h-4" /> Save Changes
            </button>
        </div>

    </form>

</div>
