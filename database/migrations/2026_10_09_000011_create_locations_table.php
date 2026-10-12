<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->references('id')->on('locations')->nullOnDelete();
            $table->string('level'); // zone/aisle/rack/shelf_level/bin
            $table->string('segment_code'); // e.g. A, 03, B
            $table->string('code'); // full code e.g. DAR-A-03-02-B
            $table->string('type'); // yard_slot/receiving_dock/storage/staging/quarantine
            $table->string('status')->default('active');
            $table->unsignedInteger('max_pallets')->nullable();
            $table->decimal('max_weight_kg', 10, 3)->nullable();
            $table->string('barcode')->nullable()->unique(); // nullable unique; MySQL allows multiple NULLs
            $table->string('path')->nullable(); // materialised path e.g. /1/5/12/
            $table->boolean('is_pickable')->default(true);
            $table->string('blocked_reason')->nullable();
            $table->foreignId('blocked_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('blocked_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['warehouse_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
