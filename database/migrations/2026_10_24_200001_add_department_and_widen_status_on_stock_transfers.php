<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('accepted_by');
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });

        DB::statement("ALTER TABLE stock_transfers MODIFY COLUMN status VARCHAR(40) NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });

        DB::statement("ALTER TABLE stock_transfers MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'draft'");
    }
};
