<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('report_key', 60);
            $table->string('frequency', 10);               // daily | weekly | monthly
            $table->unsignedTinyInteger('send_day')->nullable(); // 1–7 weekly (Mon–Sun), 1–28 monthly
            $table->unsignedTinyInteger('send_hour')->default(7); // 0–23
            $table->string('date_range', 30);              // last_7_days | last_30_days | last_week | last_month | this_month | this_year | today | yesterday
            $table->string('format', 10)->default('excel'); // excel | pdf
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->json('recipients');                    // array of email strings
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('is_active');
            $table->index('next_run_at');
        });

        Schema::table('report_requests', function (Blueprint $table) {
            $table->foreignId('scheduled_report_id')
                ->nullable()
                ->after('user_id')
                ->constrained('scheduled_reports')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('report_requests', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\ScheduledReport::class);
            $table->dropColumn('scheduled_report_id');
        });

        Schema::dropIfExists('scheduled_reports');
    }
};
