<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scanner_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('route_name', 100)->nullable();
            $table->string('raw_value', 500);
            $table->string('resolved_type', 50)->nullable();
            $table->unsignedBigInteger('resolved_id')->nullable();
            $table->boolean('success');
            $table->string('error_message', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['resolved_type', 'resolved_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_events');
    }
};
