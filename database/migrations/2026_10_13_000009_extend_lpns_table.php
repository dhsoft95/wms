<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lpns', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->after('status');
            $table->foreignId('current_location_id')->nullable()->constrained('locations')->nullOnDelete()->after('warehouse_id');
            $table->foreignId('grn_id')->nullable()->after('current_location_id');
            $table->datetime('printed_at')->nullable()->after('grn_id');
            $table->unsignedInteger('print_count')->default(0)->after('printed_at');
        });

        // Add FK for grn_id separately (grns table is created before this migration)
        Schema::table('lpns', function (Blueprint $table) {
            $table->foreign('grn_id')->references('id')->on('grns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lpns', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropForeign(['current_location_id']);
            $table->dropForeign(['grn_id']);
            $table->dropColumn(['warehouse_id', 'current_location_id', 'grn_id', 'printed_at', 'print_count']);
        });
    }
};
