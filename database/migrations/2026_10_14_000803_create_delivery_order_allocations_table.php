<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_order_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_line_id')->constrained('delivery_order_lines')->cascadeOnDelete();
            $table->foreignId('stock_balance_id')->constrained('stock_balances')->restrictOnDelete();
            $table->foreignId('stock_reservation_id')->nullable()->constrained('stock_reservations')->nullOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('lpn_id')->nullable()->constrained('lpns')->nullOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->decimal('qty', 14, 4);
            $table->string('status', 20)->default('reserved');
            $table->timestamps();
            $table->index('delivery_order_line_id');
            $table->index('stock_balance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_order_allocations');
    }
};
