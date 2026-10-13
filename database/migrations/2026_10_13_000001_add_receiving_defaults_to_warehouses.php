<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('default_receiving_dock_location_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete()
                ->after('is_active');

            $table->foreignId('default_quarantine_location_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete()
                ->after('default_receiving_dock_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['default_receiving_dock_location_id']);
            $table->dropForeign(['default_quarantine_location_id']);
            $table->dropColumn(['default_receiving_dock_location_id', 'default_quarantine_location_id']);
        });
    }
};
