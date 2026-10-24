<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_order_approvals', function (Blueprint $table) {
            $table->foreignId('approval_chain_id')
                ->nullable()
                ->after('delivery_order_id')
                ->constrained('approval_chains')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_order_approvals', function (Blueprint $table) {
            $table->dropForeign(['approval_chain_id']);
            $table->dropColumn('approval_chain_id');
        });
    }
};
