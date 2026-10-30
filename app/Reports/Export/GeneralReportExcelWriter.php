<?php

declare(strict_types=1);

namespace App\Reports\Export;

use App\Models\User;
use App\Services\Reports\GeneralReportService;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

final class GeneralReportExcelWriter
{
    private const BRAND     = 'A22631';
    private const TOTALS_BG = 'F3F4F6';
    private const GREY      = '6B7280';

    // Row positions (1-indexed per spec; writer appends sequentially)
    private const ROWS_BEFORE_DATA = 15; // rows 1-15 before first data row

    private const COLS_RECEIPTS = [
        ['key' => 'reception_date',      'label' => 'RECEPTION DATE',     'fmt' => 'date',   'align' => 'left'],
        ['key' => 'grn_number',          'label' => 'GRN NUMBER',          'fmt' => 'text',   'align' => 'left'],
        ['key' => 'reception_reference', 'label' => 'RECEPTION REFERENCE', 'fmt' => 'text',   'align' => 'left'],
        ['key' => 'supplier',            'label' => 'SUPPLIER',            'fmt' => 'text',   'align' => 'left'],
        ['key' => 'department',          'label' => 'DEPARTMENT',          'fmt' => 'text',   'align' => 'left'],
        ['key' => 'po_number',           'label' => 'PO NUMBER',           'fmt' => 'text',   'align' => 'left'],
        ['key' => 'part_number',         'label' => 'PART NUMBER',         'fmt' => 'text',   'align' => 'left'],
        ['key' => 'item_description',    'label' => 'ITEM DESCRIPTION',    'fmt' => 'text',   'align' => 'left'],
        ['key' => 'conformity',          'label' => 'CONFORMITY',          'fmt' => 'text',   'align' => 'center'],
        ['key' => 'uom',                 'label' => 'UOM',                 'fmt' => 'text',   'align' => 'center'],
        ['key' => 'quantity_received',   'label' => 'QUANTITY RECEIVED',   'fmt' => 'number', 'align' => 'right'],
        ['key' => 'location',            'label' => 'LOCATION',            'fmt' => 'text',   'align' => 'left'],
        ['key' => 'mic_number',          'label' => 'MIC NUMBER',          'fmt' => 'text',   'align' => 'left'],
        ['key' => 'serial_number',       'label' => 'SERIAL NUMBER',       'fmt' => 'text',   'align' => 'left'],
        ['key' => 'single_value',        'label' => 'SINGLE VALUE',        'fmt' => 'money',  'align' => 'right'],
        ['key' => 'total_value',         'label' => 'TOTAL VALUE',         'fmt' => 'money',  'align' => 'right'],
    ];

    private const COLS_DESPATCHES = [
        ['key' => 'shipping_date',    'label' => 'SHIPPING DATE',    'fmt' => 'date',   'align' => 'left'],
        ['key' => 'grn_number',       'label' => 'GRN NUMBER',        'fmt' => 'text',   'align' => 'left'],
        ['key' => 'order_number',     'label' => 'ORDER NUMBER',      'fmt' => 'text',   'align' => 'left'],
        ['key' => 'req_department',   'label' => 'DEPARTMENT',        'fmt' => 'text',   'align' => 'left'],
        ['key' => 'site_name',        'label' => 'SITE NAME',         'fmt' => 'text',   'align' => 'left'],
        ['key' => 'sub_contractor',   'label' => 'SUB-CONTRACTOR',    'fmt' => 'text',   'align' => 'left'],
        ['key' => 'requestor',        'label' => 'REQUESTOR',         'fmt' => 'text',   'align' => 'left'],
        ['key' => 'approver',         'label' => 'APPROVER',          'fmt' => 'text',   'align' => 'left'],
        ['key' => 'stock_department', 'label' => 'DEPARTMENT',        'fmt' => 'text',   'align' => 'left'],
        ['key' => 'po_number',        'label' => 'PO NUMBER',         'fmt' => 'text',   'align' => 'left'],
        ['key' => 'part_number',      'label' => 'PART NUMBER',       'fmt' => 'text',   'align' => 'left'],
        ['key' => 'item_description', 'label' => 'ITEM DESCRIPTION',  'fmt' => 'text',   'align' => 'left'],
        ['key' => 'conformity',       'label' => 'CONFORMITY',        'fmt' => 'text',   'align' => 'center'],
        ['key' => 'uom',              'label' => 'UOM',               'fmt' => 'text',   'align' => 'center'],
        ['key' => 'qty_ordered',      'label' => 'QTY ORDERED',       'fmt' => 'number', 'align' => 'right'],
        ['key' => 'qty_delivered',    'label' => 'QTY DELIVERED',     'fmt' => 'number', 'align' => 'right'],
        ['key' => 'mic_number',       'label' => 'MIC NUMBER',        'fmt' => 'text',   'align' => 'left'],
        ['key' => 'serial_number',    'label' => 'SERIAL NUMBER',     'fmt' => 'text',   'align' => 'left'],
        ['key' => 'single_value',     'label' => 'SINGLE VALUE',      'fmt' => 'money',  'align' => 'right'],
        ['key' => 'stock_value',      'label' => 'STOCK VALUE',       'fmt' => 'money',  'align' => 'right'],
    ];

    private const COLS_AGEING = [
        ['key' => 'supplier_site',     'label' => 'SUPPLIER/SITE',      'fmt' => 'text',   'align' => 'left'],
        ['key' => 'inbound_reference', 'label' => 'INBOUND REFERENCE',  'fmt' => 'text',   'align' => 'left'],
        ['key' => 'date_in',           'label' => 'DATE IN',            'fmt' => 'date',   'align' => 'left'],
        ['key' => 'grn_number',        'label' => 'GRN NUMBER',         'fmt' => 'text',   'align' => 'left'],
        ['key' => 'department',        'label' => 'DEPARTMENT',         'fmt' => 'text',   'align' => 'left'],
        ['key' => 'po_number',         'label' => 'PO NUMBER',          'fmt' => 'text',   'align' => 'left'],
        ['key' => 'part_number',       'label' => 'PART NUMBER',        'fmt' => 'text',   'align' => 'left'],
        ['key' => 'item_description',  'label' => 'ITEM DESCRIPTION',   'fmt' => 'text',   'align' => 'left'],
        ['key' => 'conformity',        'label' => 'CONFORMITY',         'fmt' => 'text',   'align' => 'center'],
        ['key' => 'quantity',          'label' => 'QUANTITY',           'fmt' => 'number', 'align' => 'right'],
        ['key' => 'mic_number',        'label' => 'MIC NUMBER',         'fmt' => 'text',   'align' => 'left'],
        ['key' => 'single_value',      'label' => 'SINGLE VALUE',       'fmt' => 'money',  'align' => 'right'],
        ['key' => 'stock_value',       'label' => 'STOCK VALUE',        'fmt' => 'money',  'align' => 'right'],
        ['key' => 'stay_in_days',      'label' => 'STAY IN DAYS',       'fmt' => 'number', 'align' => 'right'],
    ];

    // Totals columns per sheet (keys whose values are summed)
    private const TOTALS_RECEIPTS   = ['quantity_received', 'total_value'];
    private const TOTALS_DESPATCHES = ['qty_ordered', 'qty_delivered', 'stock_value'];
    private const TOTALS_AGEING     = ['quantity', 'stock_value'];

    // ─── Public entry point ───────────────────────────────────────────────────

    public function write(string $filePath, array $filters, User $user, array $brand): void
    {
        $svc        = app(GeneralReportService::class);
        $receipts   = $svc->receipts($filters, $user);
        $despatches = $svc->despatches($filters, $user);
        $ageing     = $svc->stockAgeing($filters, $user);

        $maxCols = max(
            count(self::COLS_RECEIPTS),
            count(self::COLS_DESPATCHES),
            count(self::COLS_AGEING),
        );

        $options = new Options();
        $options->DEFAULT_ROW_HEIGHT = 15.0;

        // Column widths based on Sheet 2 (widest, 20 cols) — applied globally
        foreach (self::COLS_DESPATCHES as $i => $col) {
            $options->setColumnWidth($this->colWidth($col['fmt']), $i + 1);
        }

        // Merge title (row 2, 0-indexed 1) and address (row 3, 0-indexed 2) per sheet
        foreach ([count(self::COLS_RECEIPTS), count(self::COLS_DESPATCHES), count(self::COLS_AGEING)] as $si => $n) {
            $options->mergeCells(0, 1, $n - 1, 1, $si);
            $options->mergeCells(0, 2, $n - 1, 2, $si);
        }

        $writer = new Writer($options);
        $writer->openToFile($filePath);

        // Sheet 1 – Receipts
        $s1 = $writer->getCurrentSheet();
        $s1->setName('Receipts');
        $s1->setSheetView((new SheetView())->setFreezeRow(16));
        $this->writeSheet($writer, $brand, $filters, $receipts, 'RECEIPTS', self::COLS_RECEIPTS, self::TOTALS_RECEIPTS);

        // Sheet 2 – Despatches
        $writer->addNewSheetAndMakeItCurrent();
        $s2 = $writer->getCurrentSheet();
        $s2->setName('Despatches');
        $s2->setSheetView((new SheetView())->setFreezeRow(16));
        $this->writeSheet($writer, $brand, $filters, $despatches, 'DESPATCHES', self::COLS_DESPATCHES, self::TOTALS_DESPATCHES);

        // Sheet 3 – Stock Ageing
        $writer->addNewSheetAndMakeItCurrent();
        $s3 = $writer->getCurrentSheet();
        $s3->setName('Stock Ageing');
        $s3->setSheetView((new SheetView())->setFreezeRow(16));
        $this->writeSheet($writer, $brand, $filters, $ageing, 'STOCK AGEING', self::COLS_AGEING, self::TOTALS_AGEING);

        $writer->close();
    }

    // ─── Sheet writer ─────────────────────────────────────────────────────────

    private function writeSheet(
        Writer $writer,
        array $brand,
        array $filters,
        Collection $rows,
        string $sectionTitle,
        array $columns,
        array $totalKeys,
    ): void {
        $n        = count($columns);
        $empty    = array_fill(0, $n - 1, '');
        $clientName = $brand['name'] ?? config('app.name');

        $from = $filters['date_from'] ?? null;
        $to   = $filters['date_to']   ?? null;

        // ── Row 1: blank ──────────────────────────────────────────────────────
        $writer->addRow(Row::fromValues(array_fill(0, $n, '')));

        // ── Row 2: "{CLIENT} – ACTIVITY REPORT" (merged via Options) ─────────
        $titleStyle = (new Style())
            ->setFontBold(true)
            ->setFontSize(14)
            ->setFontColor(self::BRAND)
            ->setCellAlignment(CellAlignment::LEFT);
        $writer->addRow(Row::fromValues(
            array_merge([strtoupper($clientName) . ' - ACTIVITY REPORT'], $empty),
            $titleStyle,
        ));

        // ── Row 3: Company address (merged via Options) ───────────────────────
        $addrStyle = (new Style())
            ->setFontSize(9)
            ->setFontColor(self::GREY)
            ->setCellAlignment(CellAlignment::LEFT);
        $writer->addRow(Row::fromValues(
            array_merge([$brand['address'] ?? ''], $empty),
            $addrStyle,
        ));

        // ── Rows 4–5: blank ───────────────────────────────────────────────────
        $writer->addRow(Row::fromValues(array_fill(0, $n, '')));
        $writer->addRow(Row::fromValues(array_fill(0, $n, '')));

        // ── Row 6: generated timestamp | operations period ────────────────────
        $metaStyle = (new Style())->setFontSize(9)->setFontColor(self::GREY);
        $periodStr = ($from && $to)
            ? 'Operations from ' . Carbon::parse($from)->format('d/m/Y') . ' to ' . Carbon::parse($to)->format('d/m/Y')
            : '';
        $writer->addRow(new Row([
            Cell::fromValue('Generated: ' . now()->format('d/m/Y H:i'), $metaStyle),
            Cell::fromValue($periodStr, $metaStyle),
            ...array_map(fn () => Cell::fromValue('', $metaStyle), array_fill(0, $n - 2, null)),
        ]));

        // ── Row 7: report date | department ───────────────────────────────────
        $deptName = '';
        if ($filters['department_id'] ?? null) {
            $deptName = \App\Models\Department::find($filters['department_id'])?->name ?? '';
        }
        $writer->addRow(new Row([
            Cell::fromValue('Report date : ' . now()->format('d/m/Y'), $metaStyle),
            Cell::fromValue('Department : ' . ($deptName ?: 'ALL'), $metaStyle),
            ...array_map(fn () => Cell::fromValue('', $metaStyle), array_fill(0, $n - 2, null)),
        ]));

        // ── Rows 8–12: blank × 5 ─────────────────────────────────────────────
        for ($i = 0; $i < 5; $i++) {
            $writer->addRow(Row::fromValues(array_fill(0, $n, '')));
        }

        // ── Row 13: section title ─────────────────────────────────────────────
        $secStyle = (new Style())->setFontBold(true)->setFontSize(11);
        $writer->addRow(Row::fromValues(array_merge([$sectionTitle], $empty), $secStyle));

        // ── Row 14: blank ─────────────────────────────────────────────────────
        $writer->addRow(Row::fromValues(array_fill(0, $n, '')));

        // ── Row 15: column headers ────────────────────────────────────────────
        $border = new Border(
            new BorderPart(Border::BOTTOM, Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::TOP,    Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::LEFT,   Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT,  Color::BLACK, Border::WIDTH_THIN, Border::STYLE_SOLID),
        );
        $hdrStyle = (new Style())
            ->setFontBold(true)
            ->setFontColor(Color::WHITE)
            ->setFontSize(10)
            ->setBackgroundColor(self::BRAND)
            ->setBorder($border)
            ->setCellAlignment(CellAlignment::CENTER);
        $hdrCells = array_map(fn ($col) => Cell::fromValue($col['label'], $hdrStyle), $columns);
        $writer->addRow(new Row($hdrCells));

        // ── Row 16: blank (spacer before data) ───────────────────────────────
        $writer->addRow(Row::fromValues(array_fill(0, $n, '')));

        // ── Rows 17+: data ────────────────────────────────────────────────────
        foreach ($rows as $row) {
            $writer->addRow(new Row($this->buildDataCells($row, $columns)));
        }

        // ── Totals row ────────────────────────────────────────────────────────
        if ($rows->isNotEmpty()) {
            $boldBg  = (new Style())->setFontBold(true)->setBackgroundColor(self::TOTALS_BG);
            $boldBgR = (new Style())->setFontBold(true)->setBackgroundColor(self::TOTALS_BG)->setCellAlignment(CellAlignment::RIGHT);
            $totalCells = [];
            foreach ($columns as $i => $col) {
                if ($i === 0) {
                    $totalCells[] = Cell::fromValue('TOTAL', $boldBg);
                } elseif (in_array($col['key'], $totalKeys, true)) {
                    $sum = $rows->sum(fn ($r) => (float) ($r->{$col['key']} ?? 0));
                    $totalCells[] = Cell::fromValue($sum, $boldBgR);
                } else {
                    $totalCells[] = Cell::fromValue('', $boldBg);
                }
            }
            $writer->addRow(new Row($totalCells));
        }
    }

    // ─── Cell builder ─────────────────────────────────────────────────────────

    private function buildDataCells(object $row, array $columns): array
    {
        $cells = [];
        foreach ($columns as $col) {
            $val   = $row->{$col['key']} ?? null;
            $fmt   = $col['fmt'];
            $align = $col['align'];

            $baseStyle = (new Style())->setCellAlignment(match ($align) {
                'right'  => CellAlignment::RIGHT,
                'center' => CellAlignment::CENTER,
                default  => CellAlignment::LEFT,
            });

            if ($fmt === 'date' && $val !== null) {
                $dt        = $val instanceof DateTimeInterface ? $val : Carbon::parse($val);
                $dateStyle = (new Style())
                    ->setCellAlignment(CellAlignment::LEFT)
                    ->setFormat('DD/MM/YYYY');
                $cells[] = Cell::fromValue($dt, $dateStyle);
            } elseif ($fmt === 'money' && is_numeric($val)) {
                $moneyStyle = (new Style())
                    ->setCellAlignment(CellAlignment::RIGHT)
                    ->setFormat('#,##0.00');
                $cells[] = Cell::fromValue((float) $val, $moneyStyle);
            } elseif ($fmt === 'number' && is_numeric($val)) {
                $cells[] = Cell::fromValue((float) $val, $baseStyle);
            } else {
                $cells[] = Cell::fromValue($val !== null ? (string) $val : '', $baseStyle);
            }
        }
        return $cells;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function colWidth(string $fmt): float
    {
        return match ($fmt) {
            'date'   => 14.0,
            'number' => 13.0,
            'money'  => 14.0,
            default  => 22.0,
        };
    }
}
