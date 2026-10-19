<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('warehouse_transfers', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('accepted_by');
            $table->string('dn_number')->nullable()->after('idempotency_key');
            $table->string('rcn_number')->nullable()->after('dn_number');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_transfers', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'dn_number', 'rcn_number']);
        });
    }
};
