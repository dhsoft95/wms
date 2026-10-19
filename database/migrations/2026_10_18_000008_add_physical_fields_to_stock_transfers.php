<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->boolean('is_physical')->default(false)->after('status');
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_by');
            $table->timestamp('accepted_at')->nullable()->after('accepted_by');
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->after('to_virtual_warehouse_id');
        });

        Schema::table('stock_transfer_lines', function (Blueprint $table) {
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete()->after('location_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_lines', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Location::class, 'to_location_id');
            $table->dropColumn('to_location_id');
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Warehouse::class, 'to_warehouse_id');
            $table->dropColumn('to_warehouse_id');
            $table->dropColumn('accepted_at');
            $table->dropForeignIdFor(\App\Models\User::class, 'accepted_by');
            $table->dropColumn('accepted_by');
            $table->dropColumn('is_physical');
        });
    }
};
