<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('units_of_measure')
            ->where('code', 'EA')
            ->exists();

        if (! $exists) {
            DB::table('units_of_measure')->insert([
                'code'       => 'EA',
                'name'       => 'Each',
                'type'       => 'count',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // intentionally left blank — cannot safely delete if referenced
    }
};
