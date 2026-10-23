<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('is_hazardous')->default(false)->after('is_active');
            $table->text('hazard_note')->nullable()->after('is_hazardous');
            $table->string('sds_file_path')->nullable()->after('hazard_note');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['is_hazardous', 'hazard_note', 'sds_file_path']);
        });
    }
};
