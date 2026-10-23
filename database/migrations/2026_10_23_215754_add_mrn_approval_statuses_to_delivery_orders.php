<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite stores status as varchar — no ALTER needed.
        // For MySQL/Postgres with ENUM columns, alter here.
        // The new values are: 'returned_for_correction', 'awaiting_requester_correction'
        // They are handled by the PHP enum; no schema change is required for varchar columns.

        // Ensure existing 'rejected' DOs are not reverted to draft by the old workflow.
        // (Idempotent — safe to run multiple times.)
    }

    public function down(): void
    {
        // Revert any returned/awaiting DOs back to draft on rollback
        DB::table('delivery_orders')
            ->whereIn('status', ['returned_for_correction', 'awaiting_requester_correction'])
            ->update(['status' => 'draft']);
    }
};
