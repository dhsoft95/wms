<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->renameColumn('vehicle_no', 'vehicle_reg_no');
            $table->string('container_no', 50)->nullable()->after('vehicle_reg_no');
            $table->string('container_size', 20)->nullable()->after('container_no');
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn(['container_no', 'container_size']);
            $table->renameColumn('vehicle_reg_no', 'vehicle_no');
        });
    }
};
