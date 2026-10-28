<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Container;
use App\Support\PdfBrand;
use Illuminate\Support\Collection;

final class ContainerReportService
{
    public function build(Container $container): array
    {
        $container->loadMissing([
            'purchaseOrder',
            'shipperPartner',
            'consigneePartner',
            'shippingLinePartner',
            'expectedLines.item',
            'expectedLines.uom',
            'expectedLines.virtualWarehouse',
            'grns.lines.item',
            'grns.lines.uom',
            'grns.lines.varianceReasonCode',
            'grns.confirmedBy',
            'discrepancies',
        ]);

        $lines          = $this->flattenGrnLines($container);
        $varianceSummary= $this->buildVarianceSummary($lines);
        $grnSummary     = $this->buildGrnSummary($container->grns);

        return [
            'brand'          => PdfBrand::make(),
            'container'      => $container,
            'lines'          => $lines,
            'varianceSummary'=> $varianceSummary,
            'grnSummary'     => $grnSummary,
            'totalExpected'  => $lines->sum('expected_qty'),
            'totalGood'      => $lines->sum('received_good_qty'),
            'totalDamaged'   => $lines->sum('received_damaged_qty'),
            'totalVariance'  => $lines->sum('variance_qty'),
            'generatedAt'    => now()->format('d M Y H:i'),
        ];
    }

    private function flattenGrnLines(Container $container): Collection
    {
        $rows = collect();

        foreach ($container->grns as $grn) {
            foreach ($grn->lines as $line) {
                $rows->push([
                    'grn_number'           => $grn->number,
                    'grn_confirmed_at'     => $grn->confirmed_at,
                    'item_sku'             => $line->item?->sku ?? '—',
                    'item_name'            => $line->item?->name ?? '—',
                    'uom'                  => $line->uom?->code ?? '—',
                    'mic_number'           => $line->mic_number ?? '',
                    'expected_qty'         => (float) ($line->expected_qty ?? 0),
                    'received_good_qty'    => (float) ($line->received_good_qty ?? 0),
                    'received_damaged_qty' => (float) ($line->received_damaged_qty ?? 0),
                    'variance_qty'         => (float) ($line->variance_qty ?? 0),
                    'variance_type'        => $line->variance_type?->label() ?? '',
                    'variance_type_raw'    => $line->variance_type?->value ?? 'none',
                    'remarks'              => $line->remarks ?? '',
                    'is_unexpected'        => (bool) $line->is_unexpected,
                ]);
            }
        }

        return $rows->sortBy('item_sku')->values();
    }

    private function buildVarianceSummary(Collection $lines): array
    {
        $total      = $lines->count();
        $withVar    = $lines->filter(fn ($l) => $l['variance_qty'] != 0 || $l['received_damaged_qty'] > 0)->count();
        $unexpected = $lines->filter(fn ($l) => $l['is_unexpected'])->count();
        $short      = $lines->filter(fn ($l) => $l['variance_type_raw'] === 'short')->count();
        $excess     = $lines->filter(fn ($l) => $l['variance_type_raw'] === 'excess')->count();
        $damaged    = $lines->filter(fn ($l) => $l['received_damaged_qty'] > 0)->count();

        return [
            'total_lines'    => $total,
            'clean_lines'    => $total - $withVar,
            'with_variance'  => $withVar,
            'short_lines'    => $short,
            'excess_lines'   => $excess,
            'damaged_lines'  => $damaged,
            'unexpected'     => $unexpected,
        ];
    }

    private function buildGrnSummary(Collection $grns): Collection
    {
        return $grns->map(fn ($grn) => [
            'number'       => $grn->number,
            'status'       => $grn->status?->label() ?? '—',
            'confirmed_at' => $grn->confirmed_at?->format('d M Y') ?? '—',
            'confirmed_by' => $grn->confirmedBy?->name ?? '—',
            'line_count'   => $grn->lines->count(),
            'total_good'   => (float) $grn->lines->sum('received_good_qty'),
            'total_damaged'=> (float) $grn->lines->sum('received_damaged_qty'),
        ])->values();
    }
}
