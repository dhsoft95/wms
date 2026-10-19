<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Data migration: back-fill a PurchaseOrder record for every container that
 * does not already have one.  Up-only — no down() because restoring the
 * original state is not practical after the data has been migrated.
 */
return new class extends Migration
{
    public function up(): void
    {
        $containers = DB::table('containers')
            ->whereNull('purchase_order_id')
            ->orderBy('id')
            ->get();

        foreach ($containers as $container) {
            DB::transaction(function () use ($container) {
                try {
                    // ── Generate next PO number using raw DB (no Eloquent/Service) ─────
                    $poNumber = $this->nextPoNumber();

                    // ── Map container status → PO status ─────────────────────────────
                    $poStatus = match ($container->status) {
                        'released'                          => 'closed',
                        'arrived', 'parked',
                        'unloading', 'unloaded'             => 'receiving',
                        default                             => 'approved', // expected + anything else
                    };

                    $now = now()->toDateTimeString();

                    // ── Insert PurchaseOrder row ──────────────────────────────────────
                    $poId = DB::table('purchase_orders')->insertGetId([
                        'number'        => $poNumber,
                        'source'        => 'system',
                        'package_type'  => 'container',
                        'warehouse_id'  => $container->warehouse_id,
                        'container_no'  => $container->container_no,
                        'seal_no'       => $container->seal_no,
                        'size_type'     => $container->size_type ?? null,
                        'po_file_path'  => $container->po_file_path ?? null,
                        'notes'         => $container->po_number ?? null, // traceability
                        'status'        => $poStatus,
                        'container_id'  => $container->id,
                        'created_at'    => $container->created_at ?? $now,
                        'updated_at'    => $container->updated_at ?? $now,
                    ]);

                    // ── Link container back to the new PO ────────────────────────────
                    DB::table('containers')
                        ->where('id', $container->id)
                        ->update(['purchase_order_id' => $poId]);

                    // ── Migrate ContainerExpectedLines → PurchaseOrderLines ───────────
                    $lines = DB::table('container_expected_lines')
                        ->where('container_id', $container->id)
                        ->orderBy('id')
                        ->get();

                    $lineNo = 1;
                    foreach ($lines as $cel) {
                        // Skip lines without an item mapping
                        if (empty($cel->item_id) || empty($cel->uom_id)) {
                            continue;
                        }

                        DB::table('purchase_order_lines')->insert([
                            'purchase_order_id' => $poId,
                            'line_no'           => $lineNo++,
                            'item_id'           => $cel->item_id,
                            'uom_id'            => $cel->uom_id,
                            'qty'               => $cel->qty,
                            'qty_base'          => $cel->qty, // base conversion not available in migration
                            'batch_no'          => $cel->batch_no ?? null,
                            'expiry_date'       => $cel->expiry_date ?? null,
                            'notes'             => $cel->raw_description ?? null,
                            'created_at'        => $cel->created_at ?? now()->toDateTimeString(),
                            'updated_at'        => $cel->updated_at ?? now()->toDateTimeString(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning("PO data migration: skipped container #{$container->id} — {$e->getMessage()}");
                    // Re-throw so the outer DB::transaction rolls back this container
                    throw $e;
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty — data migrations are up-only.
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Generate the next PO number using a row-level lock on document_sequences.
     * Replicates the logic in DocumentNumber::next() without loading Eloquent models.
     */
    private function nextPoNumber(): string
    {
        return DB::transaction(function (): string {
            $seq = DB::table('document_sequences')
                ->where('type', 'PO')
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                throw new \RuntimeException('document_sequences row for type=PO not found.');
            }

            $number      = $seq->next_number;
            $currentYear = (int) now()->year;

            // Yearly reset logic (mirrors DocumentNumber::next)
            if ($seq->reset_yearly && $seq->last_reset_year && (int) $seq->last_reset_year < $currentYear) {
                $number = 1;
                DB::table('document_sequences')
                    ->where('type', 'PO')
                    ->update([
                        'next_number'     => $number + 1,
                        'last_reset_year' => $currentYear,
                        'updated_at'      => now()->toDateTimeString(),
                    ]);
            } else {
                DB::table('document_sequences')
                    ->where('type', 'PO')
                    ->update([
                        'next_number' => $number + 1,
                        'updated_at'  => now()->toDateTimeString(),
                    ]);

                if ($seq->reset_yearly && ! $seq->last_reset_year) {
                    DB::table('document_sequences')
                        ->where('type', 'PO')
                        ->update(['last_reset_year' => $currentYear]);
                }
            }

            $padding = (int) $seq->padding;
            $padded  = str_pad((string) $number, $padding, '0', STR_PAD_LEFT);

            if ($seq->include_year) {
                return "{$seq->prefix}-{$currentYear}-{$padded}";
            }

            return "{$seq->prefix}-{$padded}";
        });
    }
};
