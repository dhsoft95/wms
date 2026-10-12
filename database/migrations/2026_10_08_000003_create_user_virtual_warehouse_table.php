<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_virtual_warehouse', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('virtual_warehouse_id')
                ->constrained()
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'virtual_warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_virtual_warehouse');
    }
};
