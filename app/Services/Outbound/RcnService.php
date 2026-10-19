<?php

declare(strict_types=1);

namespace App\Services\Outbound;

use App\Models\DeliveryOrder;
use App\Services\Foundation\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;

final class RcnService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function generate(DeliveryOrder $do): \Barryvdh\DomPDF\PDF
    {
        $do->load([
            'lines.item',
            'lines.uom',
            'lines.allocations.batch',
            'warehouse',
            'consigneePartner',
            'dispatches.dispatchedBy',
        ]);

        $dispatch = $do->dispatches->first();

        $data = [
            'do'        => $do,
            'dispatch'  => $dispatch,
            'brandName' => $this->settings->get('brand.name', config('app.name')),
            'brandLogo' => $this->settings->get('brand.logo', null),
        ];

        return Pdf::loadView('pdf.rcn', $data)
            ->setPaper([0, 0, 595.28, 841.89], 'portrait');
    }
}
