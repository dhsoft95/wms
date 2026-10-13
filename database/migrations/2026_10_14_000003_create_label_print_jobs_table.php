<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('label_print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('label_template_id')->nullable()->constrained('label_templates')->nullOnDelete();
            $table->string('type', 30);
            $table->morphs('printable');
            $table->unsignedTinyInteger('copies')->default(1);
            $table->foreignId('printer_id')->nullable()->constrained('printers')->nullOnDelete();
            $table->string('output', 10);
            $table->boolean('is_reprint')->default(false);
            $table->foreignId('reprint_reason_code_id')->nullable()->constrained('reason_codes')->nullOnDelete();
            $table->text('reprint_note')->nullable();
            $table->foreignId('printed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('printed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_print_jobs');
    }
};
