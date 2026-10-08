<?php

namespace App\Livewire\Inbound\Containers;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';
    public string $statusFilter = 'all';

    // placeholder data
    protected array $allRows = [
        ['container' => 'MSKU1234567', 'seal' => 'TZ-998234', 'bl' => 'BL-DAR-2026-001', 'vessel' => 'COSCO Pride V.42',    'eta' => '2026-10-02', 'slot' => 'SLOT-A03', 'status' => 'unloading', 'badge' => 'badge-blue',  'label' => 'Unloading', 'type' => '40HC High Cube'],
        ['container' => 'MRKU8829102', 'seal' => 'TZ-998235', 'bl' => 'BL-DAR-2026-002', 'vessel' => 'MSC Diana V.12',      'eta' => '2026-10-03', 'slot' => 'SLOT-B12', 'status' => 'arrived',   'badge' => 'badge-amber', 'label' => 'Arrived',   'type' => '20ft Standard'],
        ['container' => 'CMAU5543219', 'seal' => 'TZ-998236', 'bl' => 'BL-DAR-2026-003', 'vessel' => 'CMA CGM Africa V.08', 'eta' => '2026-10-05', 'slot' => null,       'status' => 'expected',  'badge' => 'badge-slate', 'label' => 'Expected',  'type' => '40ft Reefer'],
        ['container' => 'MSCU4410983', 'seal' => 'TZ-998237', 'bl' => 'BL-DAR-2026-004', 'vessel' => 'MSC Diana V.12',      'eta' => '2026-10-06', 'slot' => null,       'status' => 'expected',  'badge' => 'badge-slate', 'label' => 'Expected',  'type' => '20ft Standard'],
        ['container' => 'TGHU7712045', 'seal' => 'TZ-998230', 'bl' => 'BL-DAR-2026-000', 'vessel' => 'Maersk Kowloon V.31', 'eta' => '2026-09-28', 'slot' => 'SLOT-A01', 'status' => 'released',  'badge' => 'badge-green', 'label' => 'Released',  'type' => '40ft Standard'],
    ];

    public function filteredRows(): array
    {
        return array_values(array_filter($this->allRows, function ($r) {
            $matchesStatus = $this->statusFilter === 'all' || $r['status'] === $this->statusFilter;
            $matchesSearch = $this->search === '' || str_contains(strtolower($r['container'] . $r['bl'] . $r['vessel']), strtolower($this->search));
            return $matchesStatus && $matchesSearch;
        }));
    }

    public function render(): View
    {
        return view('livewire.inbound.containers.index', [
            'rows' => $this->filteredRows(),
        ])->layout('components.layouts.app', [
            'title'           => 'Containers & Yard',
            'breadcrumbGroup' => 'Inbound',
        ]);
    }
}
