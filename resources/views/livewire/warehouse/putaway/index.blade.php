<div class="space-y-6">
    <div class="page-header">
        <div>
            <h2 class="page-title">Put-Away (RF Handheld)</h2>
            <p class="page-sub">Scanner emulator for directed put-away tasks.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
        {{-- RF handheld emulator --}}
        <div class="xl:col-span-2 flex justify-center">
            <div class="w-full max-w-[360px] bg-slate-900 rounded-[36px] p-3.5 shadow-2xl border-4 border-slate-800 text-white">
                <div class="w-24 h-1.5 bg-slate-700 rounded-full mx-auto mb-3"></div>
                <div class="bg-slate-950 rounded-3xl p-5 min-h-[520px] flex flex-col border border-slate-800">
                    <div class="flex items-center justify-between text-[11px] text-slate-400 pb-3 border-b border-slate-800">
                        <span class="font-mono">Task #{{ $activeTask['ref'] }}</span>
                        <span class="flex items-center gap-1.5 text-emerald-400 font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>Active
                        </span>
                    </div>

                    <div class="flex items-center gap-2 py-4">
                        @for ($s = 1; $s <= $activeTask['steps']; $s++)
                            <div class="flex-1 h-1 rounded-full {{ $s <= $activeTask['step'] ? 'bg-simba-600' : 'bg-slate-700' }}"></div>
                        @endfor
                    </div>
                    <p class="text-[11px] uppercase tracking-wider text-slate-500 -mt-1 mb-4">
                        Step {{ $activeTask['step'] }} of {{ $activeTask['steps'] }} · Scan target bin
                    </p>

                    <div class="space-y-4 flex-1">
                        <div>
                            <label class="block text-[11px] uppercase tracking-wider text-slate-400 mb-1.5">Source LPN</label>
                            <div class="flex items-center justify-between bg-slate-900 border border-slate-800 rounded-xl px-3 py-2.5">
                                <span class="font-mono text-sm text-simba-300">{{ $activeTask['lpn'] }}</span>
                                <x-ui.icon name="check" class="w-4 h-4 text-emerald-400" />
                            </div>
                        </div>
                        <div class="p-4 bg-slate-900 rounded-xl border border-slate-800 space-y-2 text-sm">
                            <div class="flex justify-between"><span class="text-slate-400">SKU</span><span class="font-mono font-semibold">{{ $activeTask['sku'] }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-400">Item</span><span>{{ $activeTask['item'] }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-400">Quantity</span><span class="font-semibold tabular-nums">{{ number_format($activeTask['qty']) }} bags</span></div>
                            <div class="flex justify-between pt-2 border-t border-slate-800">
                                <span class="text-slate-400">Target bin</span>
                                <span class="font-mono font-bold text-simba-300">{{ $activeTask['targetBin'] }}</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] uppercase tracking-wider text-slate-400 mb-1.5">Scan target bin</label>
                            <input
                                wire:model="scanInput"
                                wire:keydown.enter="confirmPutaway"
                                type="text"
                                placeholder="Scan or type bin code"
                                class="w-full bg-slate-900 border-2 border-simba-600 rounded-xl px-3 py-2.5 text-sm text-white font-mono uppercase focus:outline-none focus:ring-4 focus:ring-simba-600/30"
                            >
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 mt-6">
                        <button wire:click="skipTask" class="py-3 rounded-xl bg-slate-800 text-slate-300 text-sm font-semibold hover:bg-slate-700">Skip</button>
                        <button wire:click="confirmPutaway" class="col-span-2 py-3 rounded-xl bg-simba-600 hover:bg-simba-700 text-white text-sm font-semibold">Confirm Put-Away</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Queue --}}
        <div class="xl:col-span-3 space-y-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Put-Away Queue</h3>
                    <span class="badge badge-blue">{{ count($queue) }} open</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr><th>Task</th><th>LPN</th><th>SKU</th><th class="text-right">Qty</th><th>Target Bin</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($queue as $task)
                                <tr>
                                    <td class="mono font-semibold">{{ $task['ref'] }}</td>
                                    <td class="mono">{{ $task['lpn'] }}</td>
                                    <td class="mono">{{ $task['sku'] }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($task['qty']) }}</td>
                                    <td class="mono {{ $task['label'] === 'Active' ? 'text-simba-600 font-semibold' : '' }}">{{ $task['bin'] }}</td>
                                    <td><span class="badge {{ $task['badge'] }}"><span class="dot"></span>{{ $task['label'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="alert-info">
                <x-ui.icon name="info" class="w-5 h-5 shrink-0" />
                <p>Scan the target bin label. The system rejects any bin that does not match the directed location, which keeps stock positions accurate.</p>
            </div>
        </div>
    </div>
</div>
