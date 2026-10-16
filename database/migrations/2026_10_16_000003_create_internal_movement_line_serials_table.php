<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_movement_line_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internal_movement_line_id')->constrained()->cascadeOnDelete();
            $table->string('serial_number');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_movement_line_serials');
    }
};
