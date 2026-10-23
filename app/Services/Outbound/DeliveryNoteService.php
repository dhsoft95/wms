<?php

declare(strict_types=1);

namespace App\Services\Outbound;

use App\Models\DeliveryOrder;
use App\Support\PdfBrand;
use Barryvdh\DomPDF\Facade\Pdf;

final class DeliveryNoteService
{
    public function generate(DeliveryOrder $do): \Barryvdh\DomPDF\PDF
    {
        $do->load([
            'lines.item',
            'lines.uom',
            'lines.allocations.batch',
            'lines.allocations.pickTask.serials',
            'warehouse',
            'consigneePartner',
            'dispatches.dispatchedBy',
            'dispatches.gatePass',
        ]);

        $dispatch = $do->dispatches->first();

        return Pdf::loadView('pdf.delivery-note', [
            'do'       => $do,
            'dispatch' => $dispatch,
            'brand'    => PdfBrand::make(),
        ])->setPaper([0, 0, 595.28, 841.89], 'portrait');
    }
}
