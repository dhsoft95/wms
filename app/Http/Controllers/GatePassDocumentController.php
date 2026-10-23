<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GatePass;
use App\Support\PdfBrand;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class GatePassDocumentController extends Controller
{
    public function print(GatePass $gatePass): Response
    {
        $gatePass->load([
            'dispatch.deliveryOrder.consigneePartner',
            'dispatch.deliveryOrder.virtualWarehouse',
            'dispatch.dispatchedBy',
            'dispatch.transporterPartner',
            'dispatch.items.item',
            'dispatch.items.batch',
            'verifiedBy',
        ]);

        $pdf = Pdf::loadView('pdf.gate-pass', [
            'gatePass' => $gatePass,
            'dispatch' => $gatePass->dispatch,
            'brand'    => PdfBrand::make(),
        ])->setPaper([0, 0, 595.28, 841.89], 'portrait');

        return $pdf->stream('GP-' . $gatePass->number . '.pdf');
    }
}
