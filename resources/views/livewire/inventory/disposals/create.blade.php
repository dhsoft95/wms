<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">New Disposal Request</h2>
            <p class="page-sub">Submit stock for disposal by auction or destruction.</p>
        </div>
        <a href="{{ route('inventory.disposals.index') }}" wire:navigate class="btn btn-ghost">
            Back to list
        </a>
    </div>

    <div class="card">
        <div class="card-body space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="label">Virtual Warehouse</label>
                    <select wire:model.live="virtualWarehouseId" class="input w-full">
                        <option value="0">-- Select --</option>
                        @foreach($this->warehouses as $vw)
                            <option value="{{ $vw->id }}">{{ $vw->name }}</option>
                        @endforeach
                    </select>
                    @error('virtualWarehouseId') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Disposal Method</label>
                    <select wire:model.live="method" class="input w-full">
                        @foreach($this->methods as $m)
                            <option value="{{ $m->value }}">{{ $m->label() }}</option>
                        @endforeach
                    </select>
                    @error('method') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-1">
                    <label class="label">Reason</label>
                    <input wire:model="reason" type="text" class="input w-full" placeholder="Reason for disposal…">
                    @error('reason') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3 class="card-title">Lines</h3>
            <button wire:click="addLine" type="button" class="btn btn-sm btn-ghost">
                <x-ui.icon name="plus" class="w-4 h-4" /> Add Line
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="table w-full text-sm">
                <thead>
                    <tr>
                        <th class="w-48">Item</th>
                        <th class="w-36">Location</th>
                        <th class="w-36">Batch</th>
                        <th class="w-32">Stock Status</th>
                        <th class="w-24">Qty</th>
                        <th class="w-28">Est. Value</th>
                        <th class="w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $i => $line)
                        <tr wire:key="line-{{ $i }}">
                            <td>
                                <select wire:model.live="lines.{{ $i }}.item_id" class="input w-full">
                                    <option value="">-- Item --</option>
                                    @foreach($this->items as $item)
                                        <option value="{{ $item->id }}">{{ $item->sku }} — {{ $item->name }}</option>
                                    @endforeach
                                </select>
                                @error("lines.{$i}.item_id") <p class="text-error text-xs">{{ $message }}</p> @enderror
                            </td>
                            <td>
                                <select wire:model.live="lines.{{ $i }}.location_id" class="input w-full">
                                    <option value="">-- Bin --</option>
                                    @foreach($this->locations as $loc)
                                        <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                                    @endforeach
                                </select>
                                @error("lines.{$i}.location_id") <p class="text-error text-xs">{{ $message }}</p> @enderror
                            </td>
                            <td>
                                <input wire:model.live="lines.{{ $i }}.batch_id" type="number" class="input w-full" placeholder="Batch ID">
                                @error("lines.{$i}.batch_id") <p class="text-error text-xs">{{ $message }}</p> @enderror
                            </td>
                            <td>
                                <select wire:model.live="lines.{{ $i }}.stock_status" class="input w-full">
                                    <option value="obsolete">Obsolete</option>
                                    <option value="on_hold">On Hold</option>
                                    <option value="available">Available</option>
                                </select>
                            </td>
                            <td>
                                <input wire:model.live="lines.{{ $i }}.qty" type="number" min="0.0001" step="any" class="input w-full" placeholder="0">
                                @error("lines.{$i}.qty") <p class="text-error text-xs">{{ $message }}</p> @enderror
                            </td>
                            <td class="tabular-nums text-slate-500 text-xs">
                                @if(isset($line['value']) && $line['value'] !== null)
                                    {{ number_format((float) $line['value'], 2) }}
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td>
                                @if(count($lines) > 1)
                                    <button wire:click="removeLine({{ $i }})" type="button" class="btn btn-ghost btn-sm text-red-500">
                                        <x-ui.icon name="trash" class="w-4 h-4" />
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('inventory.disposals.index') }}" wire:navigate class="btn btn-ghost">Cancel</a>
        <button wire:click="save" type="button" class="btn btn-primary" wire:loading.attr="disabled">
            <span wire:loading wire:target="save" class="mr-1">Saving…</span>
            <span wire:loading.remove wire:target="save">Submit for Approval</span>
        </button>
    </div>
</div>
