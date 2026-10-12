<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Inventory\ReservationStatus;
use App\Enums\Inventory\SerialStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Item;
use App\Models\User;
use App\Notifications\StockMismatchNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class VerifyStockCommand extends Command
{
    protected $signature = 'wms:stock:verify {--fix : Rebuild balances from ledger}';
    protected $description = 'Verify stock balances match the movement ledger. Use --fix to rebuild.';

    public function handle(): int
    {
        $this->info('Verifying stock balances against the movement ledger…');

        $mismatches = [];

        StockBalance::orderBy('id')->each(function (StockBalance $balance) use (&$mismatches) {
            // Recompute expected qty_on_hand from movements
            $expectedOnHand = DB::table('stock_movements')
                ->where('item_id', $balance->item_id)
                ->where('location_id', $balance->location_id)
                ->where('virtual_warehouse_id', $balance->virtual_warehouse_id)
                ->where('stock_status', $balance->stock_status->value)
                ->where(fn ($q) => $balance->batch_id === null
                    ? $q->whereNull('batch_id')
                    : $q->where('batch_id', $balance->batch_id))
                ->where(fn ($q) => $balance->lpn_id === null
                    ? $q->whereNull('lpn_id')
                    : $q->where('lpn_id', $balance->lpn_id))
                ->sum('qty');

            $expectedOnHand = number_format((float) $expectedOnHand, 4, '.', '');

            // Recompute expected qty_allocated from active reservations
            $expectedAllocated = DB::table('stock_reservations')
                ->where('stock_balance_id', $balance->id)
                ->where('status', ReservationStatus::Active->value)
                ->sum('qty');

            $expectedAllocated = number_format((float) $expectedAllocated, 4, '.', '');

            // Verify key_hash
            $expectedHash = hash('sha256', implode('|', [
                $balance->item_id,
                $balance->batch_id ?? 0,
                $balance->lpn_id ?? 0,
                $balance->location_id,
                $balance->virtual_warehouse_id,
                $balance->stock_status->value,
            ]));

            $onHandMismatch     = bccomp((string) $balance->qty_on_hand, $expectedOnHand, 4) !== 0;
            $allocatedMismatch  = bccomp((string) $balance->qty_allocated, $expectedAllocated, 4) !== 0;
            $hashMismatch       = $balance->key_hash !== $expectedHash;

            // Serial count check: for serial-tracked items, in_stock serials at this exact key
            // must equal qty_on_hand
            $serialMismatch = false;
            $storedSerialCount = 0;
            $item = Item::find($balance->item_id);
            if ($item && $item->track_serial) {
                $storedSerialCount = DB::table('serial_numbers')
                    ->where('item_id', $balance->item_id)
                    ->where('status', SerialStatus::InStock->value)
                    ->where('location_id', $balance->location_id)
                    ->where('virtual_warehouse_id', $balance->virtual_warehouse_id)
                    ->where('stock_status', $balance->stock_status->value)
                    ->where(fn ($q) => $balance->batch_id === null
                        ? $q->whereNull('batch_id')
                        : $q->where('batch_id', $balance->batch_id))
                    ->where(fn ($q) => $balance->lpn_id === null
                        ? $q->whereNull('lpn_id')
                        : $q->where('lpn_id', $balance->lpn_id))
                    ->count();

                $expectedQty = (int) round((float) $expectedOnHand);
                $serialMismatch = $storedSerialCount !== $expectedQty;
            }

            if ($onHandMismatch || $allocatedMismatch || $hashMismatch || $serialMismatch) {
                $mismatches[] = [
                    'balance_id'           => $balance->id,
                    'stored_on_hand'       => (string) $balance->qty_on_hand,
                    'expected_on_hand'     => $expectedOnHand,
                    'stored_allocated'     => (string) $balance->qty_allocated,
                    'expected_allocated'   => $expectedAllocated,
                    'hash_ok'              => ! $hashMismatch,
                    'expected_hash'        => $expectedHash,
                    'serial_mismatch'      => $serialMismatch,
                    'serial_count'         => $storedSerialCount,
                ];
            }
        });

        if (empty($mismatches)) {
            $this->info('All stock balances are correct.');
            return self::SUCCESS;
        }

        $count = count($mismatches);
        $this->warn("{$count} mismatch(es) found:");

        foreach ($mismatches as $m) {
            $serialInfo = isset($m['serial_mismatch']) && $m['serial_mismatch']
                ? " | serials={$m['serial_count']} (expected " . (int) round((float) $m['expected_on_hand']) . ')'
                : '';
            $this->line(sprintf(
                '  Balance #%d — on_hand stored=%s expected=%s | allocated stored=%s expected=%s | hash_ok=%s%s',
                $m['balance_id'],
                $m['stored_on_hand'],
                $m['expected_on_hand'],
                $m['stored_allocated'],
                $m['expected_allocated'],
                $m['hash_ok'] ? 'yes' : 'no',
                $serialInfo,
            ));
        }

        if ($this->option('fix')) {
            $this->info('Rebuilding balances…');

            DB::transaction(function () use ($mismatches) {
                foreach ($mismatches as $m) {
                    DB::table('stock_balances')->where('id', $m['balance_id'])->update([
                        'qty_on_hand'   => $m['expected_on_hand'],
                        'qty_allocated' => $m['expected_allocated'],
                        'key_hash'      => $m['expected_hash'],
                        'updated_at'    => now(),
                    ]);
                }
            });

            // Log the rebuild in the activity log table if it exists
            Log::channel('single')->warning('wms:stock:verify --fix rebuilt ' . count($mismatches) . ' balance(s).', [
                'balance_ids' => array_column($mismatches, 'balance_id'),
            ]);

            $this->info('Done. ' . count($mismatches) . ' balance(s) rebuilt.');
            return self::SUCCESS;
        }

        // No fix: log error and send database notification to system-admins
        Log::error('Stock balance mismatches detected.', ['count' => $count, 'mismatches' => $mismatches]);

        $admins = User::role('system-admin')->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new StockMismatchNotification($count));
        }

        return self::FAILURE;
    }
}
