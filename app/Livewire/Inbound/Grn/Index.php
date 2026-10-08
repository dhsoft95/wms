<?php

namespace App\Livewire\Inbound\Grn;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';
    public string $statusFilter = 'all';

    // placeholder data
    protected array $allRows = [
        ['grn' => 'GRN-2026-089', 'container' => 'MSKU1234567', 'supplier' => 'Azam Consumer Goods Ltd',  'received' => '2026-10-02 11:30', 'warehouse' => 'DAR-General Store', 'progress' => 64, 'count' => '320/500',         'status' => 'progress',     'badge' => 'badge-blue',  'label' => 'In Progress'],
        ['grn' => 'GRN-2026-087', 'container' => 'TGHU7712045', 'supplier' => 'Azam Consumer Goods Ltd',  'received' => '2026-09-29 09:10', 'warehouse' => 'DAR-General Store', 'progress' => 97, 'count' => '388/400',         'status' => 'discrepancy',  'badge' => 'badge-red',   'label' => 'Discrepancy', 'barColor' => 'bg-red-500'],
        ['grn' => 'GRN-2026-088', 'container' => 'MRKU8829102', 'supplier' => 'Vodacom Tanzania PLC',     'received' => '2026-10-01 15:45', 'warehouse' => 'DAR-Tech Vault',    'progress' => 100,'count' => '12,800/12,800',   'status' => 'completed',    'badge' => 'badge-green', 'label' => 'Completed'],
        ['grn' => 'GRN-2026-086', 'container' => 'MSCU2209117', 'supplier' => 'Vodacom Tanzania PLC',     'received' => '2026-09-27 13:20', 'warehouse' => 'DAR-Tech Vault',    'progress' => 100,'count' => '6,000/6,000',     'status' => 'completed',    'badge' => 'badge-green', 'label' => 'Completed'],
    ];

    public function filteredRows(): array
    {
        return array_values(array_filter($this->allRows, function ($r) {
            $matchesStatus = $this->statusFilter === 'all' || $r['status'] === $this->statusFilter;
            $matchesSearch = $this->search === '' || str_contains(strtolower($r['grn'] . $r['container'] . $r['supplier']), strtolower($this->search));
            return $matchesStatus && $matchesSearch;
        }));
    }

    public function render(): View
    {
        return view('livewire.inbound.grn.index', [
            'rows' => $this->filteredRows(),
        ])->layout('components.layouts.app', [
            'title'           => 'Goods Receipt Notes',
            'breadcrumbGroup' => 'Inbound',
        ]);
    }
}
