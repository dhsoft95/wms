<?php

namespace App\Livewire\Approvals;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $statusFilter = 'all';

    // placeholder data
    public array $rows = [
        ['id' => 1, 'ref' => 'ADJ-2026-014', 'type' => 'adjustment', 'badge' => 'badge-amber', 'label' => 'Adjustment', 'details' => 'SKU-AZ-001 at DAR-A-03-02-B', 'sub' => 'Counted 450 vs system 500', 'by' => 'Onesmo Mlelwa', 'at' => '2026-10-03 09:12', 'impact' => '-50', 'impactColor' => 'text-red-600'],
        ['id' => 2, 'ref' => 'TRF-2026-031', 'type' => 'transfer',   'badge' => 'badge-violet', 'label' => 'Transfer',    'details' => '200 x SKU-VOD-SIM',            'sub' => 'DAR-Tech Vault to Zanzibar Regional Hub', 'by' => 'Neema Joseph',  'at' => '2026-10-03 08:40', 'impact' => '200',  'impactColor' => ''],
        ['id' => 3, 'ref' => 'INS-2026-022', 'type' => 'discrepancy','badge' => 'badge-red',    'label' => 'Discrepancy', 'details' => 'GRN-2026-089 shortage',         'sub' => '12 bags of SKU-AZ-001 missing',           'by' => 'Onesmo Mlelwa', 'at' => '2026-10-02 16:05', 'impact' => '-12', 'impactColor' => 'text-red-600'],
        ['id' => 4, 'ref' => 'ADJ-2026-013', 'type' => 'adjustment', 'badge' => 'badge-amber',  'label' => 'Adjustment',  'details' => 'Found stock SKU-AZ-SUG',        'sub' => 'DAR-B-01-04-A',                           'by' => 'Neema Joseph',  'at' => '2026-10-02 14:22', 'impact' => '+24', 'impactColor' => 'text-emerald-600'],
    ];

    public function filteredRows(): array
    {
        if ($this->statusFilter === 'all') {
            return $this->rows;
        }
        return array_values(array_filter($this->rows, fn($r) => $r['type'] === $this->statusFilter));
    }

    public function approve(int $id): void
    {
        $row = collect($this->rows)->firstWhere('id', $id);
        $this->rows = array_values(array_filter($this->rows, fn($r) => $r['id'] !== $id));
        $this->dispatch('toast', message: ($row['ref'] ?? '') . ' approved', type: 'success');
    }

    public function reject(int $id): void
    {
        $row = collect($this->rows)->firstWhere('id', $id);
        $this->rows = array_values(array_filter($this->rows, fn($r) => $r['id'] !== $id));
        $this->dispatch('toast', message: ($row['ref'] ?? '') . ' rejected', type: 'error');
    }

    public function render(): View
    {
        return view('livewire.approvals.index', [
            'filteredRows' => $this->filteredRows(),
            'pendingCount' => count($this->rows),
        ])->layout('components.layouts.app', [
            'title'           => 'Approvals Inbox',
            'breadcrumbGroup' => 'Overview',
        ]);
    }
}
