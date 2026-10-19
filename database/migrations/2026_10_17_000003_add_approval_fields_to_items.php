<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('variance_threshold_pct', 5, 2)->nullable()->after('allocation_rules');
            $table->decimal('variance_threshold_qty', 18, 4)->nullable()->after('variance_threshold_pct');
            $table->boolean('dispatch_requires_approval')->default(true)->after('variance_threshold_qty');
            $table->decimal('dispatch_approval_qty', 18, 4)->nullable()->after('dispatch_requires_approval');
            $table->string('adjustment_approval', 20)->default('inherit')->after('dispatch_approval_qty');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn([
                'variance_threshold_pct',
                'variance_threshold_qty',
                'dispatch_requires_approval',
                'dispatch_approval_qty',
                'adjustment_approval',
            ]);
        });
    }
};
