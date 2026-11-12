<?php

declare(strict_types=1);

namespace App\Services\Labels;

use App\Enums\LabelTemplateType;

final class LabelLayoutBuilder
{
    private const MARGIN       = 2.0;
    private const GAP          = 1.5;
    private const FIELD_ROW_H  = 7.0;
    private const COMPANY_ROW_H = 5.0;

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * @return array{elements: array, warnings: string[]}
     */
    public function build(
        LabelTemplateType $type,
        float $wMm,
        float $hMm,
        string $layout,
        string $symbology,
        array $fields,
        bool $showCompany,
        bool $showCodeText,
    ): array {
        $m  = self::MARGIN;
        $uw = $wMm - 2 * $m;

        $mainField    = $this->mainCodeField($type);
        $barcodeField = $this->barcodeField($type);

        // Strip main code and company from extra-fields; they're placed explicitly
        $extraFields = array_values(array_filter(
            $fields,
            fn($f) => $f !== $mainField && $f !== 'company_name',
        ));

        $counter = 0;
        $newId   = static function () use (&$counter): string {
            return 'e' . ++$counter;
        };

        return match ($layout) {
            'big_code'     => $this->layoutBigCode($wMm, $hMm, $m, $uw, $mainField, $barcodeField, $symbology, $extraFields, $showCompany, $showCodeText, $newId),
            'barcode_left' => $this->layoutBarcodeLeft($wMm, $hMm, $m, $uw, $mainField, $barcodeField, $symbology, $extraFields, $showCompany, $showCodeText, $newId),
            'barcode_top'  => $this->layoutBarcodeTop($wMm, $hMm, $m, $uw, $mainField, $barcodeField, $symbology, $extraFields, $showCompany, $showCodeText, $newId),
            default        => throw new \InvalidArgumentException("Unknown layout: {$layout}"),
        };
    }

    // ─── Layouts ──────────────────────────────────────────────────────────────

    /**
     * big_code: large code text at top, full-width barcode below, fields beneath.
     */
    private function layoutBigCode(
        float $wMm, float $hMm, float $m, float $uw,
        string $mainField, string $barcodeField,
        string $symbology, array $extraFields,
        bool $showCompany, bool $showCodeText,
        \Closure $newId,
    ): array {
        $elements = [];
        $warnings = [];
        $y        = $m;
        $bottomY  = $hMm - $m - ($showCompany ? self::COMPANY_ROW_H + self::GAP : 0);

        $uh = $hMm - 2 * $m;

        // Main code text — large, bold, centered
        $codeH   = max(12.0, min(20.0, $uh * 0.28));
        $elements[] = $this->fieldEl($newId(), $mainField, $m, $y, $uw, $codeH, $this->fontSize($codeH), true, 'center');
        $y += $codeH + self::GAP;

        // Barcode (QR must be square — take as much height as available, capped at label width)
        $barcodeH   = max(15.0, min(($bottomY - $y) * 0.55, 40.0));
        if ($symbology === 'qr') {
            $side       = min($uw, min(50.0, $bottomY - $y - self::GAP));
            $elements[] = $this->barcodeEl($newId(), $barcodeField, $symbology, $m + ($uw - $side) / 2, $y, $side, $side, $showCodeText);
            $y += $side + self::GAP;
        } else {
            $elements[] = $this->barcodeEl($newId(), $barcodeField, $symbology, $m, $y, $uw, $barcodeH, $showCodeText);
            $y += $barcodeH + self::GAP;
        }

        // Extra fields
        $available = $bottomY - $y;
        $maxRows   = max(0, (int) floor($available / self::FIELD_ROW_H));
        [$fieldEls, $fieldWarnings] = $this->stackFields($extraFields, $m, $y, $uw, $maxRows, $newId);
        array_push($elements, ...$fieldEls);
        array_push($warnings, ...$fieldWarnings);

        // Company at bottom
        if ($showCompany) {
            $elements[] = $this->companyEl($newId(), $m, $hMm - $m - self::COMPANY_ROW_H, $uw);
        }

        return ['elements' => $elements, 'warnings' => $warnings];
    }

    /**
     * barcode_left: barcode in left column, code text + fields in right column.
     */
    private function layoutBarcodeLeft(
        float $wMm, float $hMm, float $m, float $uw,
        string $mainField, string $barcodeField,
        string $symbology, array $extraFields,
        bool $showCompany, bool $showCodeText,
        \Closure $newId,
    ): array {
        $elements = [];
        $warnings = [];

        $uh         = $hMm - 2 * $m;
        $barcodeColW = min(max($uw * 0.40, 20.0), 50.0);
        $textColX   = $m + $barcodeColW + 2.0;
        $textColW   = max(10.0, $uw - $barcodeColW - 2.0);
        $barcodeH   = $showCompany ? $uh - self::COMPANY_ROW_H - self::GAP : $uh;

        // Barcode — left column (QR must be square, centered vertically in the column)
        if ($symbology === 'qr') {
            $side       = min($barcodeColW, $barcodeH);
            $qrOffY     = $m + ($barcodeH - $side) / 2;
            $elements[] = $this->barcodeEl($newId(), $barcodeField, $symbology, $m, $qrOffY, $side, $side, $showCodeText);
        } else {
            $elements[] = $this->barcodeEl($newId(), $barcodeField, $symbology, $m, $m, $barcodeColW, $barcodeH, $showCodeText);
        }

        // Company under barcode
        if ($showCompany) {
            $elements[] = $this->companyEl($newId(), $m, $hMm - $m - self::COMPANY_ROW_H, $barcodeColW);
        }

        $y       = $m;
        $bottomY = $hMm - $m;

        // Main code text at top of right column
        $codeH      = max(10.0, min(16.0, $uh * 0.22));
        $elements[] = $this->fieldEl($newId(), $mainField, $textColX, $y, $textColW, $codeH, $this->fontSize($codeH), true, 'left');
        $y += $codeH + self::GAP;

        // Extra fields in right column
        $available = $bottomY - $y;
        $maxRows   = max(0, (int) floor($available / self::FIELD_ROW_H));
        [$fieldEls, $fieldWarnings] = $this->stackFields($extraFields, $textColX, $y, $textColW, $maxRows, $newId);
        array_push($elements, ...$fieldEls);
        array_push($warnings, ...$fieldWarnings);

        return ['elements' => $elements, 'warnings' => $warnings];
    }

    /**
     * barcode_top: full-width barcode at top, code text below, fields beneath.
     */
    private function layoutBarcodeTop(
        float $wMm, float $hMm, float $m, float $uw,
        string $mainField, string $barcodeField,
        string $symbology, array $extraFields,
        bool $showCompany, bool $showCodeText,
        \Closure $newId,
    ): array {
        $elements = [];
        $warnings = [];
        $y        = $m;
        $bottomY  = $hMm - $m - ($showCompany ? self::COMPANY_ROW_H + self::GAP : 0);

        $uh = $hMm - 2 * $m;

        // Barcode — full width, top third of label (QR must be square)
        $barcodeH   = max(15.0, min($uh * 0.35, 45.0));
        if ($symbology === 'qr') {
            $side       = min($uw, $barcodeH);
            $elements[] = $this->barcodeEl($newId(), $barcodeField, $symbology, $m + ($uw - $side) / 2, $y, $side, $side, $showCodeText);
            $y += $side + self::GAP;
        } else {
            $elements[] = $this->barcodeEl($newId(), $barcodeField, $symbology, $m, $y, $uw, $barcodeH, $showCodeText);
            $y += $barcodeH + self::GAP;
        }

        // Separator line
        $elements[] = ['id' => $newId(), 'kind' => 'line', 'x_mm' => $m, 'y_mm' => $y, 'w_mm' => $uw, 'h_mm' => 0.3, 'thickness_mm' => 0.3];
        $y += 0.3 + self::GAP;

        // Main code text
        $codeH      = max(8.0, min(14.0, $uh * 0.14));
        $elements[] = $this->fieldEl($newId(), $mainField, $m, $y, $uw, $codeH, $this->fontSize($codeH), true, 'center');
        $y += $codeH + self::GAP;

        // Extra fields
        $available = $bottomY - $y;
        $maxRows   = max(0, (int) floor($available / self::FIELD_ROW_H));
        [$fieldEls, $fieldWarnings] = $this->stackFields($extraFields, $m, $y, $uw, $maxRows, $newId);
        array_push($elements, ...$fieldEls);
        array_push($warnings, ...$fieldWarnings);

        // Company at bottom
        if ($showCompany) {
            $elements[] = $this->companyEl($newId(), $m, $hMm - $m - self::COMPANY_ROW_H, $uw);
        }

        return ['elements' => $elements, 'warnings' => $warnings];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Stack fields top-to-bottom; drop any that exceed maxRows and record warnings.
     *
     * @return array{array, string[]}
     */
    private function stackFields(
        array $fields,
        float $x, float $y, float $w,
        int $maxRows,
        \Closure $newId,
    ): array {
        $elements = [];
        $warnings = [];
        $row      = 0;

        foreach ($fields as $field) {
            if ($row >= $maxRows) {
                $warnings[] = "Field \"{$field}\" was dropped: not enough space on the label.";
                continue;
            }
            $yPos       = $y + $row * self::FIELD_ROW_H;
            $elements[] = $this->fieldEl($newId(), $field, $x, $yPos, $w, self::FIELD_ROW_H, $this->fontSize(self::FIELD_ROW_H), false, 'left');
            $row++;
        }

        return [$elements, $warnings];
    }

    private function fieldEl(string $id, string $fieldKey, float $x, float $y, float $w, float $h, int $fontPt, bool $bold, string $align): array
    {
        return [
            'id'          => $id,
            'kind'        => 'field',
            'x_mm'        => round($x, 2),
            'y_mm'        => round($y, 2),
            'w_mm'        => round($w, 2),
            'h_mm'        => round($h, 2),
            'field_key'   => $fieldKey,
            'font_size_pt'=> $fontPt,
            'bold'        => $bold,
            'align'       => $align,
        ];
    }

    private function barcodeEl(string $id, string $fieldKey, string $symbology, float $x, float $y, float $w, float $h, bool $showText): array
    {
        return [
            'id'        => $id,
            'kind'      => 'barcode',
            'x_mm'      => round($x, 2),
            'y_mm'      => round($y, 2),
            'w_mm'      => round($w, 2),
            'h_mm'      => round($h, 2),
            'symbology' => $symbology,
            'field_key' => $fieldKey,
            'show_text' => $showText,
        ];
    }

    private function companyEl(string $id, float $x, float $y, float $w): array
    {
        return $this->fieldEl($id, 'company_name', $x, $y, $w, self::COMPANY_ROW_H, 7, false, 'center');
    }

    /** Convert row height to font point size, clamped to [6, 28]. */
    private function fontSize(float $rowHeightMm): int
    {
        return (int) max(6, min(28, round($rowHeightMm * 0.72)));
    }

    // ─── Type lookups ─────────────────────────────────────────────────────────

    public function mainCodeField(LabelTemplateType $type): string
    {
        return match ($type) {
            LabelTemplateType::Location  => 'location_code',
            LabelTemplateType::PalletLpn => 'lpn_code',
            LabelTemplateType::Item      => 'item_sku',
            LabelTemplateType::Container => 'container_no',
            LabelTemplateType::Dispatch  => 'do_number',
        };
    }

    public function barcodeField(LabelTemplateType $type): string
    {
        return match ($type) {
            LabelTemplateType::Location  => 'barcode',
            LabelTemplateType::PalletLpn => 'lpn_code',
            LabelTemplateType::Item      => 'barcode',
            LabelTemplateType::Container => 'container_no',
            LabelTemplateType::Dispatch  => 'do_number',
        };
    }
}
