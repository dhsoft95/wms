<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('type', 30);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_no', 50)->nullable();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device', 50)->default('desktop');
            $table->timestamp('posted_at')->useCurrent();
            $table->foreignId('reversed_by_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->foreignId('reverses_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            // NO updated_at per spec
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
