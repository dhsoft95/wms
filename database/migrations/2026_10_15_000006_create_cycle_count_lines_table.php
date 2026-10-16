<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('cycle_count_lines')) {
            return;
        }
        Schema::create('cycle_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_count_id')->constrained('cycle_counts')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('system_qty', 18, 4)->default('0.0000');
            $table->decimal('counted_qty', 18, 4)->nullable();
            $table->decimal('variance', 18, 4)->nullable();
            $table->timestamps();
            $table->unique(['cycle_count_id', 'location_id', 'item_id', 'batch_id'], 'ccl_unique');
            $table->index('cycle_count_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_count_lines');
    }
};
