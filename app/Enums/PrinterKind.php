<?php

declare(strict_types=1);

namespace App\Enums;

enum PrinterKind: string
{
    case PdfBrowser  = 'pdf_browser';
    case ZplNetwork  = 'zpl_network';
    case ZplDownload = 'zpl_download';

    public function label(): string
    {
        return match($this) {
            self::PdfBrowser  => 'PDF (Browser)',
            self::ZplNetwork  => 'ZPL (Network)',
            self::ZplDownload => 'ZPL (Download)',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PdfBrowser  => 'badge-slate',
            self::ZplNetwork  => 'badge-brand',
            self::ZplDownload => 'badge-amber',
        };
    }
}
