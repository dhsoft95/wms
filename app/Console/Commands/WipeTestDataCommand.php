<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WipeTestDataCommand extends Command
{
    protected $signature   = 'wms:wipe-test-data {--force : Skip the confirmation prompt}';
    protected $description = 'Truncate all transactional data while preserving master data and users.';

    // Tables to truncate, grouped for readability. Order doesn't matter — FK checks are
    // disabled for the duration of the wipe.
    private const TABLES = [
        // ── Inbound ───────────────────────────────────────────────────────────
        'grn_receipt_serials',
        'grn_variance_approvals',
        'grn_receipts',
        'grn_lines',
        'grns',
        'putaway_task_splits',
        'putaway_tasks',
        'po_issues',
        'purchase_order_approvals',
        'purchase_order_assignments',
        'purchase_order_lines',
        'purchase_orders',
        'container_discrepancies',
        'container_documents',
        'container_expected_lines',
        'container_status_history',
        'container_yard_moves',
        'containers',

        // ── Stock ledger ──────────────────────────────────────────────────────
        'stock_movement_serials',
        'stock_movements',
        'stock_transactions',
        'stock_balances',
        'stock_reservations',
        'serial_numbers',
        'batches',
        'lpns',

        // ── Inventory operations ──────────────────────────────────────────────
        'stock_adjustment_lines',
        'stock_adjustments',
        'stock_transfer_approvals',
        'stock_transfer_line_serials',
        'stock_transfer_lines',
        'stock_transfers',
        'cycle_count_lines',
        'cycle_counts',
        'stock_count_requests',

        // ── Internal / warehouse movements ────────────────────────────────────
        'internal_movement_line_serials',
        'internal_movement_lines',
        'internal_movements',
        'warehouse_transfer_lines',
        'warehouse_transfers',

        // ── Outbound ──────────────────────────────────────────────────────────
        'pick_task_serials',
        'pick_tasks',
        'pick_lists',
        'delivery_order_allocations',
        'delivery_order_approvals',
        'delivery_order_lines',
        'delivery_orders',
        'dispatch_items',
        'dispatches',
        'pod_files',
        'proof_of_deliveries',
        'gate_passes',
        'outbound_delivery_note_lines',
        'outbound_delivery_notes',

        // ── Disposal / stock-status ────────────────────────────────────────────
        'disposal_request_line_serials',
        'disposal_request_approvals',
        'disposal_request_lines',
        'disposal_requests',
        'stock_status_request_line_serials',
        'stock_status_request_approvals',
        'stock_status_request_lines',
        'stock_status_requests',

        // ── System / logs ─────────────────────────────────────────────────────
        'label_print_jobs',
        'data_imports',
        'report_requests',
        'report_summaries',
        'notifications',
        'activity_log',
    ];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('This command cannot run in production.');
            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->warn('This will permanently delete all transactional data.');
            $this->warn('Master data (items, warehouses, partners, etc.) and users are preserved.');

            if (! $this->confirm('Are you sure you want to continue?')) {
                $this->info('Aborted.');
                return self::SUCCESS;
            }
        }

        $this->info('Wiping transactional data…');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach (self::TABLES as $table) {
                DB::table($table)->truncate();
                $this->line("  <fg=gray>truncated</> {$table}");
            }

            // Reset document sequence counters so numbers restart from 1.
            DB::table('document_sequences')->update([
                'next_number'     => 1,
                'last_reset_year' => null,
            ]);
            $this->line('  <fg=gray>reset</>     document_sequences (next_number → 1)');

        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->newLine();
        $this->info('Done. ' . count(self::TABLES) . ' tables truncated.');

        return self::SUCCESS;
    }
}
