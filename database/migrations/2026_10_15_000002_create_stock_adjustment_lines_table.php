<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('stock_adjustment_lines')) {
            return;
        }
        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('lpn_id')->nullable()->constrained('lpns')->nullOnDelete();
            $table->foreignId('reason_code_id')->constrained('reason_codes')->restrictOnDelete();
            $table->decimal('qty', 18, 4);
            $table->foreignId('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->json('serials')->nullable();
            $table->timestamps();
            $table->index('stock_adjustment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_lines');
    }
};
