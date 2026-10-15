<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dispatch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained('dispatches')->cascadeOnDelete();
            $table->foreignId('lpn_id')->nullable()->constrained('lpns')->nullOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->decimal('qty', 14, 4);
            $table->boolean('is_scanned')->default(false);
            $table->timestamps();
            $table->index('dispatch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_items');
    }
};
