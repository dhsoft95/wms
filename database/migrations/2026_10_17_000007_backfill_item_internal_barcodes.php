<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $items = DB::table('items')->whereNull('deleted_at')->get();

        foreach ($items as $item) {
            $hasInternal = DB::table('item_barcodes')
                ->where('item_id', $item->id)
                ->where('type', 'internal')
                ->exists();

            if ($hasInternal) {
                continue;
            }

            $barcodeValue = $item->sku;
            $suffix = 0;

            while (DB::table('item_barcodes')
                ->where('barcode', $barcodeValue)
                ->where('item_id', '!=', $item->id)
                ->exists()) {
                $suffix++;
                $barcodeValue = $item->sku . '-' . str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
            }

            $isPrimary = ! DB::table('item_barcodes')->where('item_id', $item->id)->exists();

            DB::table('item_barcodes')->insert([
                'item_id'    => $item->id,
                'barcode'    => $barcodeValue,
                'uom_id'     => $item->base_uom_id,
                'type'       => 'internal',
                'is_primary' => $isPrimary,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void {}
};
