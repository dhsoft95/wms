<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('serial_no', 100);      // stored trimmed + uppercased
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();

            // Current position (mirrors stock key minus status)
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('virtual_warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lpn_id')->nullable()->constrained('lpns')->nullOnDelete();
            $table->string('stock_status', 30)->nullable();   // available / on_hold at current position

            // Lifecycle status
            $table->string('status', 20)->default('in_stock'); // in_stock | dispatched | written_off

            // Audit
            $table->timestamp('first_received_at')->nullable();
            $table->foreignId('last_transaction_id')->nullable()
                ->constrained('stock_transactions')->nullOnDelete();

            $table->timestamps();

            $table->unique(['item_id', 'serial_no']);
            $table->index('serial_no');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_numbers');
    }
};
