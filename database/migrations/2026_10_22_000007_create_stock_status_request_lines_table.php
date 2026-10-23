<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_status_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_status_request_id')->constrained('stock_status_requests')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->decimal('qty', 18, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_status_request_lines');
    }
};
