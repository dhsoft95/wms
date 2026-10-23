<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_requests', function (Blueprint $table) {
            $table->json('filters')->nullable()->after('date_to');
            $table->string('format', 10)->default('excel')->after('filters');
            $table->unsignedInteger('row_count')->nullable()->after('format');
            $table->timestamp('notified_at')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('report_requests', function (Blueprint $table) {
            $table->dropColumn(['filters', 'format', 'row_count', 'notified_at']);
        });
    }
};
