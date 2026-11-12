<?php

declare(strict_types=1);

namespace App\Services\Labels;

readonly class PrintResult
{
    public function __construct(
        /** 'pdf' | 'zpl' | 'sent' */
        public string  $outputType,
        /** Raw PDF bytes or ZPL string; null for 'sent' */
        public ?string $content    = null,
        /** Cache key for the PDF temp download route */
        public ?string $pdfToken   = null,
        /** Printer name, populated for 'sent' */
        public ?string $printerName = null,
    ) {}
}
