<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PurchaseOrderPdfController extends Controller
{
    public function download(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load([
            'warehouse',
            'supplierPartner',
            'consigneePartner',
            'requestedBy',
            'assignedSupervisor',
            'assignedClerk',
            'department',
            'lines.item.baseUom',
            'lines.uom',
            'approvals.user',
        ]);

        $pdf = Pdf::loadView('pdf.po', [
            'brandName' => config('app.name'),
            'po'        => $purchaseOrder,
        ])->setPaper([0, 0, 595.28, 841.89], 'portrait');

        return $pdf->stream(($purchaseOrder->number ?? 'purchase-order') . '.pdf');
    }
}
