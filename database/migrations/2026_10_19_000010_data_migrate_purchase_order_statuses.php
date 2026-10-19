<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Maps legacy PurchaseOrderStatus values to the new expanded enum.
 *
 * Old → New
 * draft               → draft                        (unchanged)
 * submitted           → pending_approval
 * approved            → approved_pending_acceptance
 * rejected            → rejected                     (unchanged)
 * receiving           → receiving_in_progress
 * closed              → verified_closed
 * cancelled           → cancelled                    (unchanged)
 */
return new class extends Migration
{
    private const MAP = [
        'submitted' => 'pending_approval',
        'approved'  => 'approved_pending_acceptance',
        'receiving' => 'receiving_in_progress',
        'closed'    => 'verified_closed',
    ];

    public function up(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('purchase_orders')
                ->where('status', $old)
                ->update(['status' => $new]);
        }
    }

    public function down(): void
    {
        $reverse = array_flip(self::MAP);
        foreach ($reverse as $new => $old) {
            DB::table('purchase_orders')
                ->where('status', $new)
                ->update(['status' => $old]);
        }
    }
};
