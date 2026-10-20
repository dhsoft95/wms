<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->timestamp('acceptance_at')->nullable()->after('submitted_at');
            $table->timestamp('assigned_at')->nullable()->after('acceptance_at');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn(['acceptance_at', 'assigned_at']);
        });
    }
};
