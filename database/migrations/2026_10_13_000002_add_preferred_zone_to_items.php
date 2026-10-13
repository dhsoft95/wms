<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('preferred_zone_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete()
                ->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['preferred_zone_id']);
            $table->dropColumn('preferred_zone_id');
        });
    }
};
