<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grn_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_id')->constrained('grns')->cascadeOnDelete();
            $table->foreignId('container_expected_line_id')->nullable()->constrained('container_expected_lines')->nullOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('virtual_warehouse_id')->constrained('virtual_warehouses');
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->decimal('expected_qty', 15, 4)->nullable();
            $table->decimal('received_good_qty', 15, 4)->default(0);
            $table->decimal('received_damaged_qty', 15, 4)->default(0);
            $table->decimal('variance_qty', 15, 4)->default(0);
            $table->string('variance_type', 20)->default('none');
            $table->foreignId('variance_reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->boolean('is_unexpected')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_lines');
    }
};
