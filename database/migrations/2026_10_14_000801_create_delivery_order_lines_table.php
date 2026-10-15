<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('requested_qty', 14, 4);
            $table->decimal('requested_qty_base', 14, 4);
            $table->foreignId('requested_batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->decimal('allocated_qty', 14, 4)->default(0);
            $table->decimal('picked_qty', 14, 4)->default(0);
            $table->decimal('short_qty', 14, 4)->default(0);
            $table->decimal('dispatched_qty', 14, 4)->default(0);
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['delivery_order_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_order_lines');
    }
};
