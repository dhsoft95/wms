<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Dispatch;
use App\Services\Outbound\DeliveryNoteService;
use App\Services\Outbound\RcnService;
use Symfony\Component\HttpFoundation\Response;

class DispatchDocumentController extends Controller
{
    public function dn(Dispatch $dispatch): Response
    {
        $do  = $dispatch->deliveryOrder;
        $pdf = app(DeliveryNoteService::class)->generate($do);

        $filename = 'ODN-' . ($dispatch->dn_number ?? $dispatch->number) . '.pdf';

        return $pdf->stream($filename);
    }

    public function rcn(Dispatch $dispatch): Response
    {
        $do  = $dispatch->deliveryOrder;
        $pdf = app(RcnService::class)->generate($do);

        $filename = 'RCN-' . ($dispatch->rcn_number ?? $dispatch->number) . '.pdf';

        return $pdf->download($filename);
    }
}
