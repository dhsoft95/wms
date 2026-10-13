<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('container_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('container_id')->constrained('containers')->cascadeOnDelete();
            $table->foreignId('expected_line_id')->nullable()->references('id')->on('container_expected_lines')->nullOnDelete();
            $table->string('type');
            $table->text('description');
            $table->foreignId('reason_code_id')->nullable()->references('id')->on('reason_codes')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_discrepancies');
    }
};
