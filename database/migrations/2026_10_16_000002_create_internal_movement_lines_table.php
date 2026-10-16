<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_movement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('from_location_id')->constrained('locations');
            $table->foreignId('to_location_id')->constrained('locations');
            $table->string('batch_no')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('qty', 18, 4);
            $table->foreignId('uom_id')->constrained('units_of_measure');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_movement_lines');
    }
};
