<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('virtual_warehouse_id')->constrained('virtual_warehouses')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->boolean('threshold_exceeded')->default(false);
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'virtual_warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
