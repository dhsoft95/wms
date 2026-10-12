<?php

namespace App\Livewire;

use App\Models\VirtualWarehouse;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class VwSwitcher extends Component
{
    public int $selected = 0; // 0 = all assigned VWs

    public function mount(): void
    {
        $this->selected = (int) session('wms_active_vw_id', 0);
    }

    public function select(int $vwId): void
    {
        $user = auth()->user();

        if ($vwId !== 0) {
            $assignedIds = $user->virtualWarehouses()
                ->active()
                ->pluck('virtual_warehouses.id')
                ->all();

            if (! in_array($vwId, $assignedIds, strict: true)) {
                return; // silently reject — cannot widen beyond assigned VWs
            }
        }

        session(['wms_active_vw_id' => $vwId ?: null]);
        $user->clearVwCache();

        $this->selected = $vwId;
    }

    public function render(): View
    {
        $user = auth()->user();

        if ($user->canAccessAllVirtualWarehouses()) {
            return view('livewire.vw-switcher', [
                'show'       => false,
                'warehouses' => collect(),
            ]);
        }

        $warehouses = $user->virtualWarehouses()->active()->orderBy('name')->get();

        return view('livewire.vw-switcher', [
            'show'       => $warehouses->count() > 1,
            'warehouses' => $warehouses,
        ]);
    }
}
