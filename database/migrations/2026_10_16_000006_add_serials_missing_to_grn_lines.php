<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn_lines', function (Blueprint $table) {
            $table->boolean('serials_missing')->default(false)->after('variance_qty');
            $table->decimal('serials_missing_qty', 18, 4)->default(0)->after('serials_missing');
        });
    }

    public function down(): void
    {
        Schema::table('grn_lines', function (Blueprint $table) {
            $table->dropColumn(['serials_missing', 'serials_missing_qty']);
        });
    }
};
