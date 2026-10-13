<?php

declare(strict_types=1);

namespace App\Services\Inbound;

use App\Enums\Inventory\LpnStatus;
use App\Models\Grn;
use App\Models\Inventory\Lpn;
use App\Models\Warehouse;
use App\Services\Foundation\DocumentNumber;
use Illuminate\Support\Facades\DB;

final class LpnService
{
    public function generate(Grn $grn): Lpn
    {
        return DB::transaction(function () use ($grn) {
            $code = DocumentNumber::next('LPN');

            return Lpn::create([
                'code'        => $code,
                'status'      => LpnStatus::Active,
                'warehouse_id'=> $grn->warehouse_id,
                'grn_id'      => $grn->id,
            ]);
        });
    }

    public function printSheet(array $lpnIds, Grn $grn): string
    {
        $lpns    = Lpn::with('grn.warehouse')->whereIn('id', $lpnIds)->get();
        $barcode = new \Com\Tecnick\Barcode\Barcode();

        Lpn::whereIn('id', $lpnIds)->each(function (Lpn $lpn) {
            $lpn->printed_at  = now();
            $lpn->print_count = $lpn->print_count + 1;
            $lpn->saveQuietly();
        });

        $labels = [];
        foreach ($lpns as $lpn) {
            $bobj     = $barcode->getBarcodeObj('C128', $lpn->code, -4, -60, 'black');
            $svg      = $bobj->getSvgCode();
            $labels[] = [
                'lpn'       => $lpn,
                'svg'       => $svg,
                'grn_number'=> $grn->number,
                'warehouse' => $grn->warehouse?->name ?? '',
            ];
        }

        return view('lpn.print', [
            'labels' => $labels,
            'grn'    => $grn,
        ])->render();
    }
}
