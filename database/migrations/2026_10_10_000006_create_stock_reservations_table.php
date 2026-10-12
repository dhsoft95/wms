<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_balance_id')->constrained('stock_balances');
            $table->decimal('qty', 18, 4);
            $table->string('reference_type', 100);
            $table->unsignedBigInteger('reference_id');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
