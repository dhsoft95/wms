<?php

declare(strict_types=1);

namespace App\Services\Labels\Data;

use App\Models\Inventory\Lpn;
use App\Models\Inventory\StockBalance;
use App\Services\Foundation\SettingsService;

final class LpnLabelData
{
    public static function build(Lpn $lpn): array
    {
        $lpn->loadMissing(['grn', 'warehouse', 'currentLocation']);

        $balances = StockBalance::with(['item.baseUom', 'batch', 'virtualWarehouse'])
            ->where('lpn_id', $lpn->id)
            ->where('qty_on_hand', '>', 0)
            ->get();

        $itemSku  = '';
        $itemName = '';
        $batchNo  = '';
        $expiry   = '';
        $qty      = '';
        $uomCode  = '';
        $vwName   = '';

        if ($balances->count() === 1) {
            $bal     = $balances->first();
            $itemSku  = $bal->item?->sku ?? '';
            $itemName = $bal->item?->name ?? '';
            $batchNo  = $bal->batch?->batch_no ?? '';
            $expiry   = $bal->batch?->expiry_date?->format('Y-m-d') ?? '';
            $qty      = rtrim(rtrim(number_format((float) $bal->qty_on_hand, 4, '.', ''), '0'), '.');
            $uomCode  = $bal->item?->baseUom?->code ?? '';
            $vwName   = $bal->virtualWarehouse?->name ?? '';
        } elseif ($balances->count() > 1) {
            $itemSku  = 'MIXED';
            $itemName = 'MIXED';
            $totalQty = $balances->sum(fn ($b) => (float) $b->qty_on_hand);
            $qty      = rtrim(rtrim(number_format($totalQty, 4, '.', ''), '0'), '.');
            $vwName   = $balances->first()?->virtualWarehouse?->name ?? '';
        }

        return [
            'company_name'  => SettingsService::get('company.name', config('app.name')),
            'lpn_code'      => $lpn->code,
            'grn_number'    => $lpn->grn?->number ?? '',
            'warehouse_name'=> $lpn->warehouse?->name ?? '',
            'item_sku'      => $itemSku,
            'item_name'     => $itemName,
            'batch_no'      => $batchNo,
            'expiry_date'   => $expiry,
            'qty'           => $qty,
            'uom_code'      => $uomCode,
            'vw_name'       => $vwName,
            'receive_date'  => $lpn->created_at?->format('Y-m-d') ?? '',
            'location_code' => $lpn->currentLocation?->code ?? '',
        ];
    }
}
