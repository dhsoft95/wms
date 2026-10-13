<?php
declare(strict_types=1);
namespace App\Services\Labels;

use App\Models\LabelTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Com\Tecnick\Barcode\Barcode;

final class LabelRenderer
{
    private const MM_TO_PT = 2.8346;

    public function toPdf(LabelTemplate $tpl, array $rows, int $copies = 1): string
    {
        $html = $this->buildPdfHtml($tpl, $rows, $copies);
        $wPt  = round($tpl->width_mm  * self::MM_TO_PT);
        $hPt  = round($tpl->height_mm * self::MM_TO_PT);

        $pdf = Pdf::loadHTML($html)
            ->setPaper([0, 0, $wPt, $hPt], 'portrait');

        return $pdf->output();
    }

    public function toZpl(LabelTemplate $tpl, array $rows, int $copies = 1): string
    {
        $out = '';
        foreach ($rows as $row) {
            for ($c = 0; $c < $copies; $c++) {
                $out .= $this->buildZplLabel($tpl, $row);
            }
        }
        return $out;
    }

    public function toSvgPreview(LabelTemplate $tpl, array $sampleRow): string
    {
        return $this->buildPreviewSvg($tpl, $sampleRow);
    }

    // ─── PDF internals ────────────────────────────────────────────────────────

    private function buildPdfHtml(LabelTemplate $tpl, array $rows, int $copies): string
    {
        $wMm = $tpl->width_mm;
        $hMm = $tpl->height_mm;

        $pages = '';
        foreach ($rows as $row) {
            for ($c = 0; $c < $copies; $c++) {
                $pages .= $this->buildPdfPage($tpl, $row, $wMm, $hMm);
            }
        }

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="utf-8">
        <style>
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family: Arial, sans-serif; }
            .label { position:relative; width:{$wMm}mm; height:{$hMm}mm; overflow:hidden; page-break-after:always; }
            .label:last-child { page-break-after:auto; }
            .el { position:absolute; }
        </style>
        </head>
        <body>{$pages}</body>
        </html>
        HTML;
    }

    private function buildPdfPage(LabelTemplate $tpl, array $row, float $wMm, float $hMm): string
    {
        $elements = $tpl->elements ?? [];
        $inner    = '';

        foreach ($elements as $el) {
            $x = $el['x_mm'] ?? 0;
            $y = $el['y_mm'] ?? 0;
            $w = $el['w_mm'] ?? 20;
            $h = $el['h_mm'] ?? 10;

            $style = "left:{$x}mm;top:{$y}mm;width:{$w}mm;height:{$h}mm;";

            switch ($el['kind'] ?? '') {
                case 'text':
                    $value    = htmlspecialchars((string) ($el['value'] ?? ''));
                    $fontSize = $el['font_size_pt'] ?? 8;
                    $bold     = ($el['bold'] ?? false) ? 'font-weight:bold;' : '';
                    $align    = $el['align'] ?? 'left';
                    $inner   .= "<div class='el' style='{$style}font-size:{$fontSize}pt;{$bold}text-align:{$align};'>{$value}</div>";
                    break;

                case 'field':
                    $key      = $el['field_key'] ?? '';
                    $value    = htmlspecialchars((string) ($row[$key] ?? ''));
                    $fontSize = $el['font_size_pt'] ?? 8;
                    $bold     = ($el['bold'] ?? false) ? 'font-weight:bold;' : '';
                    $align    = $el['align'] ?? 'left';
                    $inner   .= "<div class='el' style='{$style}font-size:{$fontSize}pt;{$bold}text-align:{$align};'>{$value}</div>";
                    break;

                case 'barcode':
                    $key   = $el['field_key'] ?? '';
                    $value = (string) ($row[$key] ?? '');
                    if ($value !== '') {
                        $inner .= $this->pdfBarcodeElement($el, $value, $style, $w, $h);
                    }
                    break;

                case 'line':
                    $t      = $el['thickness_mm'] ?? 0.5;
                    $inner .= "<div class='el' style='{$style}border-top:{$t}mm solid black;'></div>";
                    break;

                case 'box':
                    $t      = $el['thickness_mm'] ?? 0.5;
                    $inner .= "<div class='el' style='{$style}border:{$t}mm solid black;'></div>";
                    break;
            }
        }

        return "<div class='label'>{$inner}</div>";
    }

    private function pdfBarcodeElement(array $el, string $value, string $style, float $wMm, float $hMm): string
    {
        // Amendment 7: PDF barcodes are PNG at 300 dpi
        $symbology = match($el['symbology'] ?? 'code128') {
            'qr'         => 'QRCODE,H',
            'ean13'      => 'EAN13',
            'datamatrix' => 'DATAMATRIX',
            default      => 'C128',
        };

        try {
            $barcode = new Barcode();
            // Width/height in px at 300dpi: 1mm = 300/25.4 = 11.81px
            $wPx  = (int) round($wMm * 11.81);
            $hPx  = (int) round($hMm * 11.81);
            $bobj = $barcode->getBarcodeObj($symbology, $value, $wPx, $hPx);
            $png  = $bobj->getPngData(true);
            $b64  = base64_encode($png);
            return "<img class='el' src='data:image/png;base64,{$b64}' style='{$style}' />";
        } catch (\Throwable) {
            return "<div class='el' style='{$style}border:1px solid red;font-size:7pt;color:red;'>Barcode error</div>";
        }
    }

    // ─── ZPL internals ────────────────────────────────────────────────────────

    private function buildZplLabel(LabelTemplate $tpl, array $row): string
    {
        $dpi      = $tpl->dpi ?: 203;
        $elements = $tpl->elements ?? [];
        $zpl      = "^XA\n";

        foreach ($elements as $el) {
            $xDots = $this->mm2dots($el['x_mm'] ?? 0, $dpi);
            $yDots = $this->mm2dots($el['y_mm'] ?? 0, $dpi);
            $wDots = $this->mm2dots($el['w_mm'] ?? 20, $dpi);
            $hDots = $this->mm2dots($el['h_mm'] ?? 10, $dpi);

            switch ($el['kind'] ?? '') {
                case 'text':
                    $value  = $this->zplEscape((string) ($el['value'] ?? ''));
                    $szDots = $this->mm2dots(($el['font_size_pt'] ?? 8) / 2.8346, $dpi);
                    $zpl   .= "^FO{$xDots},{$yDots}^A0N,{$szDots},{$szDots}^FD{$value}^FS\n";
                    break;

                case 'field':
                    $key    = $el['field_key'] ?? '';
                    $value  = $this->zplEscape((string) ($row[$key] ?? ''));
                    $szDots = $this->mm2dots(($el['font_size_pt'] ?? 8) / 2.8346, $dpi);
                    $zpl   .= "^FO{$xDots},{$yDots}^A0N,{$szDots},{$szDots}^FD{$value}^FS\n";
                    break;

                case 'barcode':
                    $key   = $el['field_key'] ?? '';
                    $value = $this->zplEscape((string) ($row[$key] ?? ''));
                    if ($value === '') break;
                    $showText = ($el['show_text'] ?? true) ? 'Y' : 'N';
                    switch ($el['symbology'] ?? 'code128') {
                        case 'qr':
                            $mag   = max(1, (int) round($hDots / 10));
                            $zpl  .= "^FO{$xDots},{$yDots}^BQN,2,{$mag}^FDQA,{$value}^FS\n";
                            break;
                        case 'ean13':
                            $zpl  .= "^FO{$xDots},{$yDots}^BY3^BEN,{$hDots},{$showText}^FD{$value}^FS\n";
                            break;
                        case 'datamatrix':
                            $sz   = max(1, (int) round($hDots / 10));
                            $zpl  .= "^FO{$xDots},{$yDots}^BXN,{$sz},200^FD{$value}^FS\n";
                            break;
                        default:
                            $zpl  .= "^FO{$xDots},{$yDots}^BY3^BCN,{$hDots},{$showText},N,N^FD{$value}^FS\n";
                    }
                    break;

                case 'line':
                    $tDots = max(1, $this->mm2dots($el['thickness_mm'] ?? 0.5, $dpi));
                    $zpl  .= "^FO{$xDots},{$yDots}^GB{$wDots},{$tDots},{$tDots}^FS\n";
                    break;

                case 'box':
                    $tDots = max(1, $this->mm2dots($el['thickness_mm'] ?? 0.5, $dpi));
                    $zpl  .= "^FO{$xDots},{$yDots}^GB{$wDots},{$hDots},{$tDots}^FS\n";
                    break;
            }
        }

        $zpl .= "^XZ\n";
        return $zpl;
    }

    private function mm2dots(float $mm, int $dpi): int
    {
        return (int) round($mm / 25.4 * $dpi);
    }

    private function zplEscape(string $value): string
    {
        return str_replace(['^', '~'], ['\\^', '\\~'], $value);
    }

    // ─── SVG preview ──────────────────────────────────────────────────────────

    private function buildPreviewSvg(LabelTemplate $tpl, array $sampleRow): string
    {
        $wPx      = (int) round($tpl->width_mm * 3.78);
        $hPx      = (int) round($tpl->height_mm * 3.78);
        $elements = $tpl->elements ?? [];
        $inner    = '';

        foreach ($elements as $el) {
            $x = round(($el['x_mm'] ?? 0) * 3.78);
            $y = round(($el['y_mm'] ?? 0) * 3.78);
            $w = round(($el['w_mm'] ?? 20) * 3.78);
            $h = round(($el['h_mm'] ?? 10) * 3.78);

            switch ($el['kind'] ?? '') {
                case 'text':
                    $value  = htmlspecialchars((string) ($el['value'] ?? ''));
                    $sz     = $el['font_size_pt'] ?? 8;
                    $inner .= "<text x='{$x}' y='" . ($y + $sz) . "' font-size='{$sz}' font-family='Arial'>{$value}</text>";
                    break;

                case 'field':
                    $key    = $el['field_key'] ?? '';
                    $value  = htmlspecialchars((string) ($sampleRow[$key] ?? "[{$key}]"));
                    $sz     = $el['font_size_pt'] ?? 8;
                    $inner .= "<text x='{$x}' y='" . ($y + $sz) . "' font-size='{$sz}' font-family='Arial'>{$value}</text>";
                    break;

                case 'barcode':
                    $key  = $el['field_key'] ?? '';
                    $bVal = (string) ($sampleRow[$key] ?? 'SAMPLE');
                    $sym  = match($el['symbology'] ?? 'code128') {
                        'qr'         => 'QRCODE,H',
                        'ean13'      => 'EAN13',
                        'datamatrix' => 'DATAMATRIX',
                        default      => 'C128',
                    };
                    try {
                        $barcode = new Barcode();
                        $bobj    = $barcode->getBarcodeObj($sym, $bVal, (int) $w, (int) $h);
                        $svg     = $bobj->getSvgCode();
                        $inner  .= "<image x='{$x}' y='{$y}' width='{$w}' height='{$h}' href='data:image/svg+xml;base64," . base64_encode($svg) . "'/>";
                    } catch (\Throwable) {
                        $inner .= "<rect x='{$x}' y='{$y}' width='{$w}' height='{$h}' fill='none' stroke='red'/>";
                    }
                    break;

                case 'line':
                    $t      = round(($el['thickness_mm'] ?? 0.5) * 3.78);
                    $inner .= "<line x1='{$x}' y1='{$y}' x2='" . ($x + $w) . "' y2='{$y}' stroke='black' stroke-width='{$t}'/>";
                    break;

                case 'box':
                    $t      = round(($el['thickness_mm'] ?? 0.5) * 3.78);
                    $inner .= "<rect x='{$x}' y='{$y}' width='{$w}' height='{$h}' fill='none' stroke='black' stroke-width='{$t}'/>";
                    break;
            }
        }

        return "<svg xmlns='http://www.w3.org/2000/svg' width='{$wPx}' height='{$hPx}' style='border:1px solid #ccc;background:#fff;'>{$inner}</svg>";
    }
}
