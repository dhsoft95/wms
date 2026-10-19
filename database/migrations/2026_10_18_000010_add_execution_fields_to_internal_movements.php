<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('internal_movements', function (Blueprint $table) {
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
            $table->timestamp('executed_at')->nullable()->after('executed_by');
        });

        // No row-level data migration: existing Draft records remain as Draft.
        // New records will use the Submitted → Completed flow.
    }

    public function down(): void
    {
        Schema::table('internal_movements', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\User::class, 'executed_by');
            $table->dropColumn(['executed_by', 'executed_at']);
        });
    }
};
