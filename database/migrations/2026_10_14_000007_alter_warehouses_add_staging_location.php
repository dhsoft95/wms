<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('default_staging_location_id')
                  ->nullable()
                  ->after('is_active')
                  ->constrained('locations')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['default_staging_location_id']);
            $table->dropColumn('default_staging_location_id');
        });
    }
};
