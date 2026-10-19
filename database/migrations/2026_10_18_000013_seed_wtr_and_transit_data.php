<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Insert WTR document sequence if not exists
        DB::table('document_sequences')->insertOrIgnore([[
            'type'             => 'WTR',
            'prefix'           => 'WTR',
            'next_number'      => 1,
            'padding'          => 4,
            'include_year'     => true,
            'reset_yearly'     => true,
            'last_reset_year'  => date('Y'),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]]);

        // 2. For each warehouse create an IN-TRANSIT location if none exists
        $warehouses = DB::table('warehouses')->get();

        foreach ($warehouses as $wh) {
            $exists = DB::table('locations')
                ->where('warehouse_id', $wh->id)
                ->where('type', 'transit')
                ->exists();

            if (! $exists) {
                DB::table('locations')->insert([
                    'code'         => 'TRANSIT-' . strtoupper($wh->code ?? $wh->id),
                    'warehouse_id' => $wh->id,
                    'segment_code' => 'TRANSIT',
                    'level'        => 'bin',
                    'type'         => 'transit',
                    'status'       => 'active',
                    'is_pickable'  => false,
                    'path'         => '/TRANSIT',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // intentional no-op: data migration only
    }
};
