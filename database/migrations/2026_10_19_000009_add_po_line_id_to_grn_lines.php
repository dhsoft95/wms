<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn_lines', function (Blueprint $table) {
            $table->foreignId('purchase_order_line_id')
                ->nullable()
                ->after('container_expected_line_id')
                ->constrained('purchase_order_lines')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grn_lines', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_line_id']);
            $table->dropColumn('purchase_order_line_id');
        });
    }
};
