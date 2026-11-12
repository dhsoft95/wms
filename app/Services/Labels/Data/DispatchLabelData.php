<?php

declare(strict_types=1);

namespace App\Services\Labels\Data;

use App\Models\Dispatch;
use App\Services\Foundation\SettingsService;

final class DispatchLabelData
{
    public static function build(Dispatch $dispatch): array
    {
        $dispatch->loadMissing(['deliveryOrder.consigneePartner', 'deliveryOrder.warehouse']);

        $do = $dispatch->deliveryOrder;

        return [
            'company_name'  => SettingsService::get('company.name', config('app.name')),
            'do_number'     => $do?->number ?? '',
            'partner_name'  => $do?->consigneePartner?->name ?? '',
            'warehouse_name'=> $do?->warehouse?->name ?? '',
            'item_sku'      => '',
            'qty'           => '',
        ];
    }
}
