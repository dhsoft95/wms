<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn_receipts', function (Blueprint $table) {
            $table->json('serial_nos')->nullable()->after('lpn_id');
        });
    }

    public function down(): void
    {
        Schema::table('grn_receipts', function (Blueprint $table) {
            $table->dropColumn('serial_nos');
        });
    }
};
