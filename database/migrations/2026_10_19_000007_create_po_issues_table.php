<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete();
            $table->foreignId('grn_id')
                ->nullable()
                ->constrained('grns')
                ->nullOnDelete();
            $table->foreignId('raised_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('description');
            $table->string('attachment_path')->nullable();
            $table->string('status', 20)->default('open'); // open, resolved
            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('stock_adjustment_id')
                ->nullable()
                ->constrained('stock_adjustments')
                ->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_issues');
    }
};
