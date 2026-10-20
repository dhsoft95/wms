<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('report_type', 60);
            $table->string('scope_hash', 64)->index();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('language', 10)->default('en');
            $table->text('summary_text');
            $table->string('provider', 30)->default('gemini');
            $table->string('model', 80)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['report_type', 'scope_hash', 'period_start', 'period_end', 'language'], 'rpt_summaries_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_summaries');
    }
};
