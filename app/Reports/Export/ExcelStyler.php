<?php

declare(strict_types=1);

namespace App\Reports\Export;

use App\Contracts\Reports\ReportDefinition;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

final class ExcelStyler
{
    private const BRAND     = 'A22631';
    private const TOTALS_BG = 'F3F4F6';
    private const ADDR_COLOR = '6B7280';

    private const FORMAT_WIDTHS = [
        'text'   => 24.0,
        'number' => 13.0,
        'date'   => 14.0,
        'enum'   => 18.0,
    ];

    public function write(
        string $filePath,
        ReportDefinition $definition,
        Collection $rows,
        array $brand,
        array $filters = [],
    ): void {
        $columns  = $definition->columns();
        $colCount = count($columns);
        $keys     = array_column($columns, 'key');

        $options = new Options();
        $options->DEFAULT_ROW_HEIGHT = 15.0;

        foreach ($columns as $i => $col) {
            $options->setColumnWidth(
                self::FORMAT_WIDTHS[$col['format'] ?? 'text'] ?? 20.0,
                $i + 1   // 1-indexed for setColumnWidth
            );
        }

        // Merge company header rows (cols are 0-indexed for mergeCells)
        $options->mergeCells(0, 1, $colCount - 1, 1); // row 1: company name
        $options->mergeCells(0, 2, $colCount - 1, 2); // row 2: address / blank
        $options->mergeCells(0, 3, $colCount - 1, 3); // row 3: report label
        $options->mergeCells(0, 4, $colCount - 1, 4); // row 4: period

        $writer = new Writer($options);
        $writer->openToFile($filePath);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName(mb_substr($definition->label(), 0, 31));

        // Freeze below the 5 company-header + col-header rows
        $sheet->setSheetView((new SheetView())->setFreezeRow(6));

        // Auto filter on header row (row 5, 0-indexed cols)
        $sheet->setAutoFilter(new AutoFilter(0, 5, $colCount - 1, 5));

        $empty = array_fill(0, $colCount - 1, '');

        // ── Row 1: Company name ────────────────────────────────────────────
        $nameStyle = (new Style())
            ->setFontBold(true)
            ->setFontSize(14)
            ->setFontColor(self::BRAND)
            ->setCellAlignment(CellAlignment::LEFT);
        $writer->addRow(Row::fromValues(
            array_merge([$brand['name'] ?: config('app.name')], $empty),
            $nameStyle
        ));

        // ── Row 2: Address ────────────────────────────────────────────────
        $addrStyle = (new Style())
            ->setFontSize(9)
            ->setFontColor(self::ADDR_COLOR);
        $writer->addRow(Row::fromValues(
            array_merge([$brand['address'] ?: ''], $empty),
            $addrStyle
        ));

        // ── Row 3: Report label ───────────────────────────────────────────
        $labelStyle = (new Style())->setFontBold(true)->setFontSize(11);
        $writer->addRow(Row::fromValues(
            array_merge([$definition->label()], $empty),
            $labelStyle
        ));

        // ── Row 4: Period ─────────────────────────────────────────────────
        $periodStyle = (new Style())->setFontSize(9)->setFontColor(self::ADDR_COLOR);
        $writer->addRow(Row::fromValues(
            array_merge([$this->formatPeriod($filters)], $empty),
            $periodStyle
        ));

        // ── Row 5: Column headers ─────────────────────────────────────────
        $headerStyle = (new Style())
            ->setFontBold(true)
            ->setFontColor(Color::WHITE)
            ->setFontSize(10)
            ->setBackgroundColor(self::BRAND);
        $headerCells = array_map(
            fn ($col) => Cell::fromValue($col['label'], $headerStyle),
            $columns
        );
        $writer->addRow(new Row($headerCells));

        // ── Data rows ─────────────────────────────────────────────────────
        foreach ($rows as $row) {
            $cells = [];
            foreach ($columns as $col) {
                $val   = data_get($row, $col['key']);
                $fmt   = $col['format'] ?? 'text';
                $align = $col['align']  ?? 'left';

                if ($val instanceof \BackedEnum) {
                    $val = method_exists($val, 'label') ? $val->label() : $val->value;
                }

                $cellStyle = (new Style())->setCellAlignment(match ($align) {
                    'right'  => CellAlignment::RIGHT,
                    'center' => CellAlignment::CENTER,
                    default  => CellAlignment::LEFT,
                });

                if ($fmt === 'number' && is_numeric($val)) {
                    $cells[] = Cell::fromValue((float) $val, $cellStyle);
                } elseif ($fmt === 'date' && $val !== null) {
                    $dt = $val instanceof DateTimeInterface ? $val : Carbon::parse($val);
                    $dateStyle = (new Style())
                        ->setCellAlignment(match ($align) {
                            'right'  => CellAlignment::RIGHT,
                            'center' => CellAlignment::CENTER,
                            default  => CellAlignment::LEFT,
                        })
                        ->setFormat('DD/MM/YYYY');
                    $cells[] = Cell::fromValue($dt, $dateStyle);
                } else {
                    $cells[] = Cell::fromValue($val !== null ? (string) $val : '', $cellStyle);
                }
            }
            $writer->addRow(new Row($cells));
        }

        // ── Totals row ────────────────────────────────────────────────────
        $totals = $definition->totals($rows);
        if ($totals !== null) {
            $boldBg = (new Style())
                ->setFontBold(true)
                ->setBackgroundColor(self::TOTALS_BG);
            $boldBgR = (new Style())
                ->setFontBold(true)
                ->setBackgroundColor(self::TOTALS_BG)
                ->setCellAlignment(CellAlignment::RIGHT);

            $totalCells = [];
            foreach ($columns as $i => $col) {
                if ($i === 0) {
                    $totalCells[] = Cell::fromValue('Total', $boldBg);
                } elseif (array_key_exists($col['key'], $totals)) {
                    $totalCells[] = Cell::fromValue((float) $totals[$col['key']], $boldBgR);
                } else {
                    $totalCells[] = Cell::fromValue('', $boldBg);
                }
            }
            $writer->addRow(new Row($totalCells));
        }

        $writer->close();
    }

    private function formatPeriod(array $filters): string
    {
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            return 'Period: '
                . Carbon::parse($filters['date_from'])->format('d M Y')
                . ' – '
                . Carbon::parse($filters['date_to'])->format('d M Y');
        }
        if (!empty($filters['as_of_date'])) {
            return 'As at: ' . Carbon::parse($filters['as_of_date'])->format('d M Y');
        }
        return 'Generated: ' . now()->format('d M Y H:i');
    }
}
