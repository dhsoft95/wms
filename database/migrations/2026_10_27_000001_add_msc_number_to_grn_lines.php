<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn_lines', function (Blueprint $table) {
            $table->string('msc_number', 100)->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('grn_lines', function (Blueprint $table) {
            $table->dropColumn('msc_number');
        });
    }
};
