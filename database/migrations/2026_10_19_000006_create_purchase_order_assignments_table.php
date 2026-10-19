<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete();
            $table->string('type', 20); // supervisor, clerk
            $table->foreignId('assigned_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('assigned_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('previous_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_assignments');
    }
};
