<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Grn;
use App\Models\Inventory\StockTransaction;
use App\Services\Inbound\GrnWorkflow;
use Illuminate\Console\Command;

class GrnRepairCommand extends Command
{
    protected $signature   = 'wms:grn:repair {number : GRN number (e.g. GRN-2026-0001)}';
    protected $description = 'Re-post missing stock for a confirmed GRN. Idempotent — safe to run multiple times.';

    public function handle(GrnWorkflow $workflow): int
    {
        $number = $this->argument('number');
        $grn    = Grn::where('number', $number)->first();

        if ($grn === null) {
            $this->error("GRN '{$number}' not found.");
            return self::FAILURE;
        }

        $postableStatuses = ['received', 'putaway_in_progress', 'putaway_complete', 'closed'];
        if (! in_array($grn->status->value, $postableStatuses, true)) {
            $this->error("GRN '{$number}' is in status '{$grn->status->value}'. Only confirmed GRNs can be repaired.");
            return self::FAILURE;
        }

        // Check existing stock transactions using the same idempotency key pattern
        $receiptIds   = $grn->receipts()->pluck('grn_receipts.id');
        $existingKeys = StockTransaction::whereIn(
            'idempotency_key',
            $receiptIds->map(fn ($id) => "grn:{$grn->id}:receipt:{$id}:confirm")
        )->pluck('idempotency_key')->count();

        if ($existingKeys > 0 && $existingKeys >= $receiptIds->count()) {
            $this->info("GRN '{$number}' already has stock transactions for all {$existingKeys} receipt(s). Nothing to repair.");
            return self::SUCCESS;
        }

        $this->info("Repairing GRN '{$number}' ({$grn->status->label()}, {$receiptIds->count()} receipts, {$existingKeys} already posted)…");

        try {
            $count = $workflow->postStock($grn);
            $this->info("Done. Called ledger->receive() {$count} time(s) — existing transactions were skipped via idempotency.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Repair failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
