<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">
                Item Categories
                <span class="ml-2 align-middle text-sm font-medium text-slate-400 dark:text-slate-500">{{ $this->categories->count() }}</span>
            </h2>
            <p class="page-sub">Organize products into categories for filtering and reporting.</p>
        </div>
        <button type="button" wire:click="openCreateCategory" class="btn btn-primary">
            <x-ui.icon name="plus" class="w-4 h-4" />
            New Category
        </button>
    </div>

    {{-- Table card --}}
    <div class="card overflow-hidden">

        {{-- Search toolbar --}}
        <div class="flex items-center gap-3 p-4 border-b border-white/40 dark:border-white/[0.08]">
            <div class="relative flex-1 max-w-sm">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    wire:model.live="search"
                    type="search"
                    placeholder="Search categories…"
                    class="input w-full pl-9"
                />
            </div>
        </div>

        {{-- Table --}}
        <div wire:loading.class="opacity-50">
            <x-ui.table class="min-w-[560px]">

                <thead>
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Parent</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 w-14 sr-only">Actions</th>
                </tr>
                </thead>

                <tbody wire:loading.remove class="divide-y divide-white/50 dark:divide-white/[0.06]">
                @forelse ($this->categories as $category)
                    <tr wire:key="cat-{{ $category->id }}"
                        class="transition hover:bg-white/30 dark:hover:bg-white/[0.04]">

                        <td class="px-4 py-3.5 font-medium text-slate-900 dark:text-slate-100">
                            {{ $category->name }}
                        </td>

                        <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400">
                            {{ $category->parent?->name ?? '—' }}
                        </td>

                        <td class="px-4 py-3.5">
                            @if ($category->is_active)
                                <span class="badge badge-green">Active</span>
                            @else
                                <span class="badge badge-slate">Inactive</span>
                            @endif
                        </td>

                        <td class="px-4 py-3.5">
                            <x-ui.row-actions :label="'Actions for ' . $category->name">
                                <x-ui.row-actions.item icon="pencil"  label="Edit"              wire:click="openEditCategory({{ $category->id }})" />
                                <x-ui.row-actions.item icon="users"   label="Responsible Users" wire:click="openUsersModal({{ $category->id }})" />
                                <x-ui.row-actions.divider />
                                @if ($category->is_active)
                                    <x-ui.row-actions.item icon="x"     label="Deactivate" tone="warning" wire:click="toggleActive({{ $category->id }})" />
                                @else
                                    <x-ui.row-actions.item icon="check" label="Activate"   tone="success" wire:click="toggleActive({{ $category->id }})" />
                                @endif
                            </x-ui.row-actions>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-16 text-center">
                            <x-ui.icon name="tag" class="mx-auto h-8 w-8 text-slate-300 dark:text-slate-600" />
                            <p class="mt-3 font-medium text-slate-900 dark:text-white">No categories found</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ $search ? 'Try changing your search term.' : 'Create your first category using the button above.' }}
                            </p>
                        </td>
                    </tr>
                @endforelse
                </tbody>

                <tbody wire:loading class="divide-y divide-white/50 dark:divide-white/[0.06]">
                    @for ($i = 0; $i < 5; $i++)
                        <tr class="skeleton-row">
                            <td class="px-4"><div class="skeleton-cell w-32"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-24"></div></td>
                            <td class="px-4"><div class="skeleton-cell w-14"></div></td>
                            <td class="px-4 w-14"><div class="skeleton-cell w-8 ml-auto"></div></td>
                        </tr>
                    @endfor
                </tbody>

            </x-ui.table>
        </div>

    </div>

    {{-- ─── Category Create / Edit Modal ───────────────────────────────────────── --}}

    @if ($showCategoryModal)
        <x-ui.modal
            :title="$editingId ? 'Edit Category' : 'New Category'"
            wireClose="closeCategoryModal"
            maxWidth="md"
        >
            {{-- Name --}}
            <div>
                <label class="label" for="cat-name">Name <span class="required-mark">*</span></label>
                <input
                    wire:model="name"
                    id="cat-name"
                    type="text"
                    placeholder="e.g. Electronics"
                    class="input w-full @error('name') border-red-400 @enderror"
                    autofocus
                />
                @error('name')
                    <p class="hint hint-error mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Parent Category --}}
            <div>
                <label class="label" for="cat-parent">Parent Category</label>
                <select wire:model="parentId" id="cat-parent"
                        class="input w-full @error('parentId') border-red-400 @enderror">
                    <option value="">None (top-level)</option>
                    @foreach ($this->parentOptions as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </select>
                @error('parentId')
                    <p class="hint hint-error mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Active --}}
            <label class="flex items-center gap-3 cursor-pointer">
                <input
                    wire:model="isActive"
                    type="checkbox"
                    class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800"
                />
                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Active</span>
            </label>
            @error('isActive')
                <p class="hint hint-error">{{ $message }}</p>
            @enderror

            <x-slot:footer>
                <button type="button" wire:click="closeCategoryModal" class="btn btn-secondary">Cancel</button>
                <button type="button" wire:click="saveCategory" class="btn btn-primary">
                    <x-ui.icon name="{{ $editingId ? 'check' : 'plus' }}" class="w-4 h-4" />
                    {{ $editingId ? 'Update Category' : 'Create Category' }}
                </button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    {{-- ─── Responsible Users Modal ─────────────────────────────────────────── --}}

    @if ($showUsersModal)
        <x-ui.modal
            :title="'Responsible Users  ' . ($this->managedCategory?->name ?? '')"
            wireClose="closeUsersModal"
            maxWidth="lg"
        >
            {{-- Current assignments --}}
            @if ($this->assignments->isNotEmpty())
                <div class="-mx-6 -mt-5 border-b border-slate-100 dark:border-white/[0.08]">
                    <ul class="divide-y divide-slate-100 dark:divide-white/[0.06]">
                        @foreach ($this->assignments as $assignment)
                            <li wire:key="assign-{{ $assignment->id }}"
                                class="flex items-center gap-3 px-6 py-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full
                                            bg-simba-50 dark:bg-simba-500/20 text-xs font-bold
                                            text-simba-700 dark:text-simba-300">
                                    {{ $assignment->user?->initials() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">
                                        {{ $assignment->user?->name }}
                                    </p>
                                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ $assignment->warehouse?->name }}
                                        @if ($assignment->warehouse?->code)
                                            <span class="mono">({{ $assignment->warehouse->code }})</span>
                                        @endif
                                    </p>
                                </div>
                                <button
                                    wire:click="removeUser({{ $assignment->id }})"
                                    wire:confirm="Remove {{ $assignment->user?->name }} from this category?"
                                    class="shrink-0 rounded p-1.5 text-slate-400 hover:text-red-500
                                           dark:hover:text-red-400 transition"
                                    title="Remove assignment"
                                >
                                    <x-ui.icon name="trash" class="h-4 w-4" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="rounded-lg border border-dashed border-slate-200 dark:border-white/[0.08] px-5 py-6 text-center">
                    <x-ui.icon name="users" class="mx-auto h-7 w-7 text-slate-300 dark:text-slate-600" />
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">No users assigned yet.</p>
                </div>
            @endif

            {{-- Add user form --}}
            <div class="space-y-3 pt-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Add Responsible User
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="add-user">User</label>
                        <select wire:model="addUserId" id="add-user"
                                class="input w-full @error('addUserId') border-red-400 @enderror">
                            <option value="">Select user…</option>
                            @foreach ($this->userOptions as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                        @error('addUserId')
                            <p class="hint hint-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="label" for="add-warehouse">Warehouse</label>
                        <select wire:model="addWarehouseId" id="add-warehouse"
                                class="input w-full @error('addWarehouseId') border-red-400 @enderror">
                            <option value="">Select warehouse…</option>
                            @foreach ($this->warehouseOptions as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                            @endforeach
                        </select>
                        @error('addWarehouseId')
                            <p class="hint hint-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <x-slot:footer>
                <button type="button" wire:click="closeUsersModal" class="btn btn-secondary">Done</button>
                <button type="button" wire:click="addUser" class="btn btn-primary">
                    <x-ui.icon name="plus" class="w-4 h-4" /> Assign User
                </button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

</div>
