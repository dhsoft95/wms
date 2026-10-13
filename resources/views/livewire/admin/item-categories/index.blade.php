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
    </div>

    {{-- Two-column layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        {{-- LEFT: Category list (2/3) --}}
        <div class="lg:col-span-2 card overflow-hidden">

            {{-- Search --}}
            <div class="flex items-center gap-3 p-4 border-b border-white/40 dark:border-white/[0.08]">
                <div class="relative flex-1">
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
                            class="transition hover:bg-white/30 dark:hover:bg-white/[0.04] {{ $editing === $category->id ? 'bg-simba-50/40 dark:bg-simba-950/20' : '' }}">

                            {{-- Name --}}
                            <td class="px-4 py-3.5 font-medium text-slate-900 dark:text-slate-100">
                                {{ $category->name }}
                            </td>

                            {{-- Parent --}}
                            <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400">
                                {{ $category->parent?->name ?? '—' }}
                            </td>

                            {{-- Status --}}
                            <td class="px-4 py-3.5">
                                @if ($category->is_active)
                                    <span class="badge badge-green">Active</span>
                                @else
                                    <span class="badge badge-slate">Inactive</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-3.5">
                                <x-ui.row-actions :label="'Actions for ' . $category->name">
                                    <x-ui.row-actions.item icon="pencil" label="Edit" wire:click="edit({{ $category->id }})" />
                                    <x-ui.row-actions.divider />
                                    @if (!$category->is_active)
                                        <x-ui.row-actions.item icon="check" label="Activate" tone="success" wire:click="toggleActive({{ $category->id }})" />
                                    @else
                                        <x-ui.row-actions.item icon="x" label="Deactivate" tone="warning" wire:click="toggleActive({{ $category->id }})" />
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
                                    {{ $search ? 'Try changing your search term.' : 'Create your first category using the form.' }}
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

        {{-- RIGHT: Create / Edit form (1/3) --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ $editing ? 'Edit Category' : 'New Category' }}</h3>
                @if ($editing)
                    <button type="button" wire:click="cancel" class="btn btn-secondary btn-sm">
                        <x-ui.icon name="x" class="w-4 h-4" /> Cancel
                    </button>
                @endif
            </div>

            <form wire:submit="save">
                <div class="card-body space-y-5">

                    {{-- Name --}}
                    <div>
                        <label class="label" for="cat-name">Name <span class="text-red-500">*</span></label>
                        <input
                            wire:model="name"
                            id="cat-name"
                            type="text"
                            placeholder="e.g. Electronics"
                            class="input w-full @error('name') border-red-400 @enderror"
                        />
                        @error('name')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Parent Category --}}
                    <div>
                        <label class="label" for="cat-parent">Parent Category</label>
                        <select
                            wire:model="parentId"
                            id="cat-parent"
                            class="input w-full @error('parentId') border-red-400 @enderror"
                        >
                            <option value="">None (top-level)</option>
                            @foreach ($this->parentOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                        @error('parentId')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Is Active --}}
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
                            <p class="hint mt-0.5">Inactive categories won't appear in item forms.</p>
                        </div>
                    </label>

                </div>

                <div class="card-footer flex items-center gap-3">
                    <button type="submit" class="btn btn-primary">
                        <x-ui.icon name="{{ $editing ? 'check' : 'plus' }}" class="w-4 h-4" />
                        {{ $editing ? 'Update Category' : 'Create Category' }}
                    </button>
                    @if ($editing)
                        <button type="button" wire:click="cancel" class="btn btn-secondary">Cancel</button>
                    @endif
                </div>
            </form>
        </div>

    </div>

</div>
