<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('accepted_by');
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->string('status', 40)->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
            $table->string('status', 20)->default('draft')->change();
        });
    }
};
