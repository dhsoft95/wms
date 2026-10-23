<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposal_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disposal_request_id')->constrained('disposal_requests')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->decimal('qty', 18, 4);
            $table->decimal('value', 18, 4)->nullable();
            $table->text('reason')->nullable();
            $table->string('stock_status')->nullable(); // snapshot of stock status at disposal time
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposal_request_lines');
    }
};
