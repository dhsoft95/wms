<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $year = now()->year;

        // Find the highest sequential number already used in the containers table
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::table('containers')
                ->where('number', 'like', "CTR-{$year}-%")
                ->pluck('number');
            $max = $rows->map(fn ($n) => (int) substr(strrchr($n, '-'), 1))->max() ?? 0;
        } else {
            $max = DB::table('containers')
                ->where('number', 'like', "CTR-{$year}-%")
                ->selectRaw("MAX(CAST(SUBSTRING_INDEX(number, '-', -1) AS UNSIGNED)) as max_seq")
                ->value('max_seq') ?? 0;
        }

        $required = $max + 1;

        // Only advance the sequence — never go backwards
        DB::table('document_sequences')
            ->where('type', 'CTR')
            ->where('next_number', '<', $required)
            ->update(['next_number' => $required]);
    }

    public function down(): void {}
};
