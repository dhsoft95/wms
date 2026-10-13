<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('container_expected_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained('containers')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->foreignId('item_id')->nullable()->references('id')->on('items')->nullOnDelete();
            $table->string('raw_sku')->nullable();
            $table->string('raw_description')->nullable();
            $table->decimal('qty', 18, 4);
            $table->foreignId('uom_id')->nullable()->references('id')->on('units_of_measure')->nullOnDelete();
            $table->string('batch_no')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->foreignId('virtual_warehouse_id')->nullable()->references('id')->on('virtual_warehouses')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['container_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_expected_lines');
    }
};
