<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn_receipts', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('condition');
            $table->decimal('unit_price', 15, 4)->nullable()->after('currency');
            $table->decimal('line_total', 15, 4)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('grn_receipts', function (Blueprint $table) {
            $table->dropColumn(['currency', 'unit_price', 'line_total']);
        });
    }
};
