<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('head_user_id')
                ->nullable()
                ->after('code')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('report_email')->nullable()->after('head_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('head_user_id');
            $table->dropColumn('report_email');
        });
    }
};
