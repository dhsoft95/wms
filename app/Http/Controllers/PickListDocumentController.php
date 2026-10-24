<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PickList;
use App\Support\PdfBrand;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class PickListDocumentController extends Controller
{
    public function pdf(PickList $pickList): Response
    {
        $this->authorize('view', $pickList->deliveryOrder);

        $pickList->load([
            'deliveryOrder.consigneePartner',
            'deliveryOrder.warehouse',
            'assignedTo',
            'tasks' => fn ($q) => $q->orderBy('sequence'),
            'tasks.location',
            'tasks.item.baseUom',
            'tasks.batch',
        ]);

        $pdf = Pdf::loadView('pdf.pick-list', [
            'pickList' => $pickList,
            'brand'    => PdfBrand::make(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('PCK-' . $pickList->number . '.pdf');
    }
}
