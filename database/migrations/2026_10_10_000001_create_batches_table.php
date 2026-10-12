<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('batch_no', 100);
            $table->date('expiry_date')->nullable();
            $table->date('manufactured_date')->nullable();
            $table->timestamps();
            $table->unique(['item_id', 'batch_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
