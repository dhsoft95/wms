<?php

declare(strict_types=1);

namespace App\Services\Labels\Data;

use App\Models\Item;
use App\Services\Foundation\SettingsService;

final class ItemLabelData
{
    public static function build(Item $item, ?string $batchNo = null, ?string $expiryDate = null, ?string $qty = null): array
    {
        $item->loadMissing(['baseUom', 'barcodes']);

        $primaryBarcode = $item->barcodes->first()?->barcode ?? $item->sku;

        return [
            'company_name' => SettingsService::get('company.name', config('app.name')),
            'item_sku'     => $item->sku,
            'item_name'    => $item->name,
            'barcode'      => $primaryBarcode,
            'batch_no'     => $batchNo ?? '',
            'expiry_date'  => $expiryDate ?? '',
            'qty'          => $qty ?? '',
            'uom_code'     => $item->baseUom?->code ?? '',
        ];
    }
}
