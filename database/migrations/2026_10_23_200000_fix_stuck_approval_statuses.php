<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Move POs stuck in pending_approval where the dept head already approved
 * (level-1 row exists) but the status never advanced.
 * These should be approved_pending_acceptance so the WM can accept them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // POs stuck at the old intermediate WM-approval status
        DB::table('purchase_orders')
            ->where('status', 'pending_warehouse_manager_approval')
            ->update(['status' => 'approved_pending_acceptance', 'approved_at' => now()]);

        // POs where HOD approved but status never advanced
        $stuckPoIds = DB::table('purchase_order_approvals')
            ->where('level', 1)
            ->where('action', 'approved')
            ->pluck('purchase_order_id')
            ->unique();

        if ($stuckPoIds->isNotEmpty()) {
            DB::table('purchase_orders')
                ->whereIn('id', $stuckPoIds)
                ->where('status', 'pending_approval')
                ->update(['status' => 'approved_pending_acceptance', 'approved_at' => now()]);
        }
    }

    public function down(): void
    {
        // Re-identify the same POs by checking for a level-1 approved row
        $poIds = DB::table('purchase_order_approvals')
            ->where('level', 1)
            ->where('action', 'approved')
            ->pluck('purchase_order_id')
            ->unique();

        if ($poIds->isNotEmpty()) {
            DB::table('purchase_orders')
                ->whereIn('id', $poIds)
                ->where('status', 'approved_pending_acceptance')
                ->update(['status' => 'pending_approval', 'approved_at' => null]);
        }
    }
};
