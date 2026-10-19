<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('name', 'gate-security')
            ->update(['name' => 'warehouse-officer']);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'warehouse-officer')
            ->update(['name' => 'gate-security']);
    }
};
