<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn_receipts', function (Blueprint $table) {
            $table->string('damage_remark', 500)->nullable()->after('condition');
        });
    }

    public function down(): void
    {
        Schema::table('grn_receipts', function (Blueprint $table) {
            $table->dropColumn('damage_remark');
        });
    }
};
