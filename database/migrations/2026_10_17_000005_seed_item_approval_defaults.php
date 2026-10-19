<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $pct = DB::table('settings')->where('key', 'operations.variance_approval_threshold_pct')->value('value');
        $qty = DB::table('settings')->where('key', 'operations.variance_approval_threshold_qty')->value('value');

        DB::table('items')->update([
            'variance_threshold_pct'      => $pct ?: null,
            'variance_threshold_qty'      => $qty ?: null,
            'dispatch_requires_approval'  => true,
        ]);
    }

    public function down(): void {}
};
