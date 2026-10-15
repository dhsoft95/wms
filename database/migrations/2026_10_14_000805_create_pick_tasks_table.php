<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pick_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pick_list_id')->constrained('pick_lists')->cascadeOnDelete();
            $table->foreignId('delivery_order_allocation_id')->constrained('delivery_order_allocations')->restrictOnDelete();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('lpn_id')->nullable()->constrained('lpns')->nullOnDelete();
            $table->decimal('qty_to_pick', 14, 4);
            $table->decimal('qty_picked', 14, 4)->default(0);
            $table->decimal('qty_short', 14, 4)->default(0);
            $table->string('status', 20)->default('open');
            $table->foreignId('picked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('picked_at')->nullable();
            $table->foreignId('stock_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->foreignId('short_reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->timestamps();
            $table->index(['pick_list_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pick_tasks');
    }
};
