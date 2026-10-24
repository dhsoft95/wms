<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pick_lists: restrict → cascade (DO delete cascades to pick lists → tasks)
        Schema::table('pick_lists', function (Blueprint $table) {
            $table->dropForeign(['delivery_order_id']);
            $table->foreign('delivery_order_id')
                ->references('id')->on('delivery_orders')
                ->cascadeOnDelete();
        });

        // pick_tasks: allocation FK restrict → cascade (allocation delete also cascades to tasks)
        Schema::table('pick_tasks', function (Blueprint $table) {
            $table->dropForeign(['delivery_order_allocation_id']);
            $table->foreign('delivery_order_allocation_id')
                ->references('id')->on('delivery_order_allocations')
                ->cascadeOnDelete();
        });

        // dispatches: restrict → cascade
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropForeign(['delivery_order_id']);
            $table->foreign('delivery_order_id')
                ->references('id')->on('delivery_orders')
                ->cascadeOnDelete();
        });

        // gate_passes: restrict → cascade (dispatch delete cascades to gate passes)
        Schema::table('gate_passes', function (Blueprint $table) {
            $table->dropForeign(['dispatch_id']);
            $table->foreign('dispatch_id')
                ->references('id')->on('dispatches')
                ->cascadeOnDelete();
        });

        // proof_of_deliveries: restrict → cascade
        Schema::table('proof_of_deliveries', function (Blueprint $table) {
            $table->dropForeign(['delivery_order_id']);
            $table->foreign('delivery_order_id')
                ->references('id')->on('delivery_orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pick_lists', function (Blueprint $table) {
            $table->dropForeign(['delivery_order_id']);
            $table->foreign('delivery_order_id')
                ->references('id')->on('delivery_orders')
                ->restrictOnDelete();
        });

        Schema::table('pick_tasks', function (Blueprint $table) {
            $table->dropForeign(['delivery_order_allocation_id']);
            $table->foreign('delivery_order_allocation_id')
                ->references('id')->on('delivery_order_allocations')
                ->restrictOnDelete();
        });

        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropForeign(['delivery_order_id']);
            $table->foreign('delivery_order_id')
                ->references('id')->on('delivery_orders')
                ->restrictOnDelete();
        });

        Schema::table('gate_passes', function (Blueprint $table) {
            $table->dropForeign(['dispatch_id']);
            $table->foreign('dispatch_id')
                ->references('id')->on('dispatches')
                ->restrictOnDelete();
        });

        Schema::table('proof_of_deliveries', function (Blueprint $table) {
            $table->dropForeign(['delivery_order_id']);
            $table->foreign('delivery_order_id')
                ->references('id')->on('delivery_orders')
                ->cascadeOnDelete();
        });
    }
};
