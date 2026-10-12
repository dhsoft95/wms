<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('lpn_id')->nullable()->constrained('lpns')->nullOnDelete();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('virtual_warehouse_id')->constrained();
            $table->string('stock_status', 20);
            $table->decimal('qty', 18, 4);           // signed
            $table->decimal('entered_qty', 18, 4);
            $table->foreignId('entered_uom_id')->constrained('units_of_measure');
            $table->timestamp('created_at')->useCurrent();
            // NO updated_at per spec
            $table->index('stock_transaction_id');
            $table->index('item_id');
            $table->index('batch_id');
            $table->index('lpn_id');
            $table->index('location_id');
            $table->index('virtual_warehouse_id');
            $table->index('stock_status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
