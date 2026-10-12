<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            // Stock key columns
            $table->foreignId('item_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('lpn_id')->nullable()->constrained('lpns')->nullOnDelete();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('virtual_warehouse_id')->constrained();
            $table->string('stock_status', 20);
            // Computed hash for MySQL NULL-safe uniqueness
            $table->char('key_hash', 64)->unique();
            // Quantities
            $table->decimal('qty_on_hand', 18, 4)->default(0);
            $table->decimal('qty_allocated', 18, 4)->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            // No created_at per spec
            $table->index('item_id');
            $table->index('location_id');
            $table->index('virtual_warehouse_id');
            $table->index('stock_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
