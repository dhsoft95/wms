<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete();
            // null = category-level approval
            $table->foreignId('approval_chain_id')
                ->nullable()
                ->constrained('approval_chains')
                ->nullOnDelete();
            // set for category-level approvals
            $table->foreignId('item_category_id')
                ->nullable()
                ->constrained('item_categories')
                ->nullOnDelete();
            $table->unsignedTinyInteger('level');
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('action', 20); // approved, rejected, returned
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_approvals');
    }
};
