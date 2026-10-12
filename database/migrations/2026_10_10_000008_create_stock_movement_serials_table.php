<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movement_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('serial_number_id')->constrained('serial_numbers')->cascadeOnDelete();

            $table->unique(['stock_movement_id', 'serial_number_id']);
            $table->index('serial_number_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_serials');
    }
};
