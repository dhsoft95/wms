<?php

declare(strict_types=1);

namespace App\Reports\Activity;

use App\Contracts\Reports\ReportDefinition;
use App\Enums\GrnStatus;
use App\Models\GrnLine;
use App\Models\User;
use App\Models\VirtualWarehouse;
use App\Reports\Export\GeneralReportExcelWriter;
use App\Services\Reports\GeneralReportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class GeneralReport implements ReportDefinition
{
    public function key(): string   { return 'general'; }
    public function label(): string { return 'General / Comprehensive Report'; }
    public function group(): string { return 'Activity'; }
    public function periodType(): string { return 'range'; }
    public function pdfEnabled(): bool   { return false; }

    /**
     * Column definitions for the Livewire preview (Sheet 1 — Receipts subset).
     * The full 3-sheet Excel is built by writeExcel().
     */
    public function columns(): array
    {
        return [
            ['key' => 'grn.confirmed_at',         'label' => 'Reception Date', 'align' => 'left',   'format' => 'date'],
            ['key' => 'grn.number',                'label' => 'GRN No',         'align' => 'left',   'format' => 'text'],
            ['key' => 'grn.supplierPartner.name',  'label' => 'Supplier',       'align' => 'left',   'format' => 'text'],
            ['key' => 'grn.purchaseOrder.number',  'label' => 'PO No',          'align' => 'left',   'format' => 'text'],
            ['key' => 'item.part_number',          'label' => 'Part No',        'align' => 'left',   'format' => 'text'],
            ['key' => 'item.name',                 'label' => 'Description',    'align' => 'left',   'format' => 'text'],
            ['key' => 'uom.code',                  'label' => 'UOM',            'align' => 'center', 'format' => 'text'],
            ['key' => 'received_good_qty',         'label' => 'Qty Received',   'align' => 'right',  'format' => 'number'],
        ];
    }

    /** Preview query: returns receipts (Sheet 1) for the Livewire table. */
    public function query(array $filters, User $user): Builder
    {
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->format('Y-m-d');
        $dateTo   = $filters['date_to']   ?? now()->format('Y-m-d');

        return GrnLine::query()
            ->join('grns', 'grns.id', '=', 'grn_lines.grn_id')
            ->whereNotIn('grns.status', [GrnStatus::Cancelled->value])
            ->whereNotNull('grns.confirmed_at')
            ->whereBetween('grns.confirmed_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->select('grn_lines.*')
            ->with(['grn.supplierPartner', 'grn.purchaseOrder', 'item', 'uom'])
            ->when(! $user->canAccessAllVirtualWarehouses(), function ($q) use ($user) {
                $q->whereIn('grn_lines.virtual_warehouse_id', $user->accessibleVirtualWarehouseIds());
            })
            ->when($filters['department_id'] ?? null, function ($q, $deptId) {
                $vwIds = VirtualWarehouse::where('department_id', $deptId)->pluck('id');
                $q->whereIn('grn_lines.virtual_warehouse_id', $vwIds);
            })
            ->orderBy('grns.confirmed_at', 'desc')
            ->orderBy('grns.id', 'desc');
    }

    public function summary(Collection $rows): array
    {
        return [
            'Lines'    => number_format($rows->count()),
            'Qty In'   => number_format((float) $rows->sum('received_good_qty'), 2),
        ];
    }

    public function totals(Collection $rows): ?array
    {
        return ['received_good_qty' => $rows->sum('received_good_qty')];
    }

    // ─── Multi-sheet Excel export ─────────────────────────────────────────────

    /**
     * Called by GenerateReportJob instead of ExcelStyler when this method exists.
     * Writes the 3-sheet XLSX directly to $filePath.
     */
    public function writeExcel(string $filePath, array $filters, User $user, array $brand): void
    {
        app(GeneralReportExcelWriter::class)->write($filePath, $filters, $user, $brand);
    }

    /** Storage filename for this report. */
    public function excelFilename(array $filters): string
    {
        $dept    = '';
        if ($filters['department_id'] ?? null) {
            $dept = \App\Models\Department::find($filters['department_id'])?->name ?? 'DEPT';
            $dept = preg_replace('/[^A-Za-z0-9_]/', '_', strtoupper($dept));
        }
        $label   = $dept ?: 'ALL';
        $dateSfx = Carbon::parse($filters['date_to'] ?? now())->format('d_M_Y');
        return "GENERAL_REPORT_{$label}_{$dateSfx}.xlsx";
    }
}
