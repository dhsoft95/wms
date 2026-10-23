<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Grn;
use App\Support\PdfBrand;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class GrnDocumentController extends Controller
{
    public function download(Grn $grn): Response
    {
        abort_unless($grn->number !== null, 404, 'GRN document is only available after receiving is complete.');

        $grn->load([
            'warehouse',
            'purchaseOrder.supplierPartner',
            'receivingDock',
            'lines.item.baseUom',
            'lines.uom',
            'lines.receipts.uom',
            'confirmedBy',
        ]);

        $pdf = Pdf::loadView('pdf.grn', [
            'brand' => PdfBrand::make(),
            'grn'   => $grn,
        ])->setPaper([0, 0, 595.28, 841.89], 'portrait');

        return $pdf->stream($grn->number . '.pdf');
    }
}
