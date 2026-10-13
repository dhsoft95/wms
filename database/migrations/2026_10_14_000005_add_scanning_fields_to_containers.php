<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->string('internal_number', 50)->nullable()->unique()->after('number');
            $table->string('iso_number', 11)->nullable()->unique()->after('internal_number');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->after('iso_number');
        });
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['internal_number', 'iso_number', 'warehouse_id']);
        });
    }
};
