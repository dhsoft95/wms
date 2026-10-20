<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('putaway_task_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('putaway_task_id')->constrained('putaway_tasks')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations');
            $table->decimal('qty', 14, 4);
            $table->json('serial_nos')->nullable();
            $table->foreignId('stock_transaction_id')->nullable()->constrained('stock_transactions');
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('putaway_task_splits');
    }
};
