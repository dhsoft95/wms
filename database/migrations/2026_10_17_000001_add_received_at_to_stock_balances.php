<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('qty_allocated');
        });

        // Backfill received_at from earliest inbound stock_movement per balance key
        // Inbound = qty > 0
        // NOTE: SQLite does not support table aliases in UPDATE; use table name directly.
        DB::statement("
            UPDATE stock_balances
            SET received_at = (
                SELECT MIN(sm.created_at)
                FROM stock_movements sm
                WHERE sm.item_id              = stock_balances.item_id
                  AND sm.virtual_warehouse_id = stock_balances.virtual_warehouse_id
                  AND sm.stock_status         = stock_balances.stock_status
                  AND COALESCE(sm.batch_id,    -1) = COALESCE(stock_balances.batch_id,    -1)
                  AND COALESCE(sm.lpn_id,      -1) = COALESCE(stock_balances.lpn_id,      -1)
                  AND COALESCE(sm.location_id, -1) = COALESCE(stock_balances.location_id, -1)
                  AND sm.qty > 0
            )
            WHERE stock_balances.received_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropColumn('received_at');
        });
    }
};
