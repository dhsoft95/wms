<div class="space-y-6">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h2 class="page-title">Edit Partner</h2>
            <p class="page-sub">Update details for <strong>{{ $partner->name }}</strong>.</p>
        </div>
        <a href="{{ route('admin.partners.show', $partner) }}" wire:navigate class="btn btn-secondary">
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
                        <label class="label" for="code">
                            Code <span class="text-red-500">*</span>
                        </label>
                        <input
                            wire:model="code"
                            id="code"
                            type="text"
                            placeholder="e.g. SUPP-001"
                            class="input mono uppercase @error('code') border-red-400 @enderror"
                        />
                        <p class="hint">Changing the code may affect existing references.</p>
                        @error('code')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="label" for="name">
                            Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            wire:model="name"
                            id="name"
                            type="text"
                            placeholder="e.g. Acme Supplies Ltd"
                            class="input @error('name') border-red-400 @enderror"
                        />
                        @error('name')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="label" for="tin">
                            TIN <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span>
                        </label>
                        <input
                            wire:model="tin"
                            id="tin"
                            type="text"
                            placeholder="Tax Identification Number"
                            class="input mono @error('tin') border-red-400 @enderror"
                        />
                        @error('tin')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="label" for="vrn">
                            VRN <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span>
                        </label>
                        <input
                            wire:model="vrn"
                            id="vrn"
                            type="text"
                            placeholder="VAT Registration Number"
                            class="input mono @error('vrn') border-red-400 @enderror"
                        />
                        @error('vrn')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>


            {{-- RIGHT: Contact --}}
            <div class="card h-full">
                <div class="card-header">
                    <h3 class="card-title">
                        <x-ui.icon name="phone" class="w-4 h-4" />
                        Contact Details
                    </h3>
                </div>

                <div class="card-body space-y-5">

                    <div>
                        <label class="label" for="contact_person">
                            Contact Person <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span>
                        </label>
                        <input
                            wire:model="contact_person"
                            id="contact_person"
                            type="text"
                            placeholder="e.g. John Doe"
                            class="input @error('contact_person') border-red-400 @enderror"
                        />
                        @error('contact_person')
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

                    <div>
                        <label class="label" for="email">
                            Email <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span>
                        </label>
                        <input
                            wire:model="email"
                            id="email"
                            type="email"
                            placeholder="contact@example.com"
                            class="input mono @error('email') border-red-400 @enderror"
                        />
                        @error('email')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>
        </div>


        {{-- Address Card (full-width) --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="arrows" class="w-4 h-4" />
                    Address
                </h3>
            </div>

            <div class="card-body space-y-5">

                <div>
                    <label class="label" for="address">
                        Address <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span>
                    </label>
                    <textarea
                        wire:model="address"
                        id="address"
                        rows="2"
                        placeholder="Street address, P.O. Box…"
                        class="input @error('address') border-red-400 @enderror"
                    ></textarea>
                    @error('address')
                        <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="label" for="city">
                            City <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span>
                        </label>
                        <input
                            wire:model="city"
                            id="city"
                            type="text"
                            placeholder="e.g. Dar es Salaam"
                            class="input @error('city') border-red-400 @enderror"
                        />
                        @error('city')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="label" for="country">
                            Country <span class="text-slate-400 dark:text-slate-500 font-normal">(optional)</span>
                        </label>
                        <input
                            wire:model="country"
                            id="country"
                            type="text"
                            placeholder="e.g. Tanzania"
                            class="input @error('country') border-red-400 @enderror"
                        />
                        @error('country')
                            <p class="hint !text-red-600 dark:!text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

            </div>
        </div>


        {{-- Partner Types Card (full-width) --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <x-ui.icon name="shield" class="w-4 h-4" />
                    Partner Types <span class="text-red-500">*</span>
                </h3>
            </div>

            <div class="card-body">
                <p class="hint mb-4">Select all roles this partner plays in your operations.</p>

                <div class="flex flex-wrap gap-4">
                    @foreach (\App\Enums\PartnerType::cases() as $type)
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input
                                wire:model="types"
                                type="checkbox"
                                value="{{ $type->value }}"
                                class="rounded border-slate-300 dark:border-slate-600 text-simba-600 focus:ring-simba-600 dark:bg-zinc-800"
                            />
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                {{ $type->label() }}
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('types')
                    <p class="hint !text-red-600 dark:!text-red-400 mt-3">{{ $message }}</p>
                @enderror
            </div>
        </div>


        {{-- Footer Actions --}}
        <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 pt-4
                    border-t border-white/40 dark:border-white/[0.08]">
            <a href="{{ route('admin.partners.show', $partner) }}" wire:navigate class="btn btn-secondary text-center">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary justify-center">
                <x-ui.icon name="check" class="w-4 h-4" />
                Save Changes
            </button>
        </div>

    </form>

</div>
