<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cycle_counts', function (Blueprint $table) {
            $table->foreignId('cancelled_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('posted_at');
        });
    }

    public function down(): void
    {
        Schema::table('cycle_counts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn('cancelled_at');
        });
    }
};
