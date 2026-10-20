<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Data\Inventory\StockLine;
use App\Data\Inventory\StockReference;
use App\Enums\Inventory\StockStatus;
use App\Models\Item;
use App\Models\Location;
use App\Models\VirtualWarehouse;
use App\Services\Inventory\StockLedger;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class StockDemoSeeder extends Seeder
{
    public function run(StockLedger $ledger): void
    {
        $items     = Item::active()->with('baseUom')->limit(10)->get();
        $locations = Location::active()->limit(10)->get();
        $vws       = VirtualWarehouse::active()->limit(5)->get();

        if ($items->isEmpty() || $locations->isEmpty() || $vws->isEmpty()) {
            $this->command->warn('No items/locations/VWs found  run FoundationDemoSeeder first.');
            return;
        }

        // Separate quarantine locations from general storage
        $quarantineLocations = $locations->filter(fn ($l) => $l->type->value === 'quarantine');
        $storageLocations    = $locations->filter(fn ($l) => $l->type->value !== 'quarantine');

        if ($storageLocations->isEmpty()) {
            $storageLocations = $locations;
        }

        $locA  = $storageLocations->first();
        $locB  = $storageLocations->count() > 1 ? $storageLocations->skip(1)->first() : $locA;
        $locC  = $storageLocations->count() > 2 ? $storageLocations->skip(2)->first() : $locB;
        $vwA   = $vws->first();
        $vwB   = $vws->count() > 1 ? $vws->skip(1)->first() : $vwA;
        $qrtLoc = $quarantineLocations->first();

        $ref = new StockReference(
            referenceType: 'opening',
            referenceId: null,
            referenceNo: 'OPEN-DEMO-' . now()->format('Ymd'),
        );

        // -- Group 1: simple multi-location, multi-VW balances ----------------

        // Item 1 in Loc A, VW A — above min_stock
        $item1 = $items->first();
        $this->postLine($ledger, $item1, $locA, $vwA, '120.0000', StockStatus::Available, $ref, null, null, 'Item 1 @ Loc A / VW A');

        // Item 1 also in Loc B, VW B — creates a second balance row for same item
        if ($items->count() >= 1) {
            $this->postLine($ledger, $item1, $locB, $vwB, '50.0000', StockStatus::Available, $ref, null, null, 'Item 1 @ Loc B / VW B');
        }

        // Item 2 in Loc A, VW A
        if ($items->count() >= 2) {
            $item2 = $items->skip(1)->first();
            $this->postLine($ledger, $item2, $locA, $vwA, '200.0000', StockStatus::Available, $ref, null, null, 'Item 2 @ Loc A / VW A');
        }

        // -- Group 2: batch-tracked item with expiry ---------------------------

        $batchItems = $items->filter(fn ($i) => $i->track_batch && $i->track_expiry);
        if ($batchItems->isNotEmpty()) {
            $batchItem    = $batchItems->first();
            $expiryNormal = Carbon::now()->addMonths(6);

            $this->postLine(
                $ledger, $batchItem, $locA, $vwA, '96.0000', StockStatus::Available, $ref,
                'BATCH-2025-001', $expiryNormal,
                "Batch item {$batchItem->sku} with expiry {$expiryNormal->toDateString()}"
            );

            // Near-expiry batch (within 30 days) — same item, different batch
            $expiryNear = Carbon::now()->addDays(20);
            $this->postLine(
                $ledger, $batchItem, $locB, $vwA, '24.0000', StockStatus::Available, $ref,
                'BATCH-2025-002', $expiryNear,
                "Near-expiry batch {$batchItem->sku} expiring {$expiryNear->toDateString()}"
            );
        } else {
            $this->command->warn('No batch+expiry item found — skipping near-expiry demo stock.');
        }

        // -- Group 3: batch-tracked item WITHOUT expiry ------------------------

        $batchOnlyItems = $items->filter(fn ($i) => $i->track_batch && ! $i->track_expiry);
        if ($batchOnlyItems->isNotEmpty()) {
            $batchOnlyItem = $batchOnlyItems->first();
            $this->postLine(
                $ledger, $batchOnlyItem, $locC, $vwA, '40.0000', StockStatus::Available, $ref,
                'BATCHONLY-001', null,
                "Batch-only item {$batchOnlyItem->sku}"
            );
        }

        // -- Group 4: on_hold stock (manually set) in a storage location ------

        $holdItem = $items->count() >= 3 ? $items->skip(2)->first() : $items->first();
        $this->postLine($ledger, $holdItem, $locA, $vwA, '30.0000', StockStatus::OnHold, $ref, null, null, 'On-hold stock for ' . $holdItem->sku);

        // -- Group 5: quarantine location forces on_hold ----------------------

        if ($qrtLoc !== null) {
            $qrtItem = $items->count() >= 4 ? $items->skip(3)->first() : $items->first();
            // Even though we pass Available here, the ledger will force on_hold because it's quarantine
            $this->postLine($ledger, $qrtItem, $qrtLoc, $vwA, '60.0000', StockStatus::Available, $ref, null, null, "Quarantine stock {$qrtItem->sku} — will be forced on_hold");
        } else {
            $this->command->warn('No quarantine location found skipping quarantine demo stock.');
        }

        // -- Group 6: item below min_stock threshold --------------------------
        // min_stock is 10; post only 3 units so dashboard shows low-stock alert

        $lowItem = $items->count() >= 5 ? $items->skip(4)->first() : $items->last();
        $this->postLine($ledger, $lowItem, $locB, $vwB, '3.0000', StockStatus::Available, $ref, null, null, "Low-stock item {$lowItem->sku} (below min_stock)");

        // -- Group 7: additional bulk balances for realistic dashboard numbers -

        foreach ($items->slice(5) as $idx => $item) {
            $loc  = ($idx % 2 === 0) ? $locA : $locC;
            $vw   = ($idx % 2 === 0) ? $vwA  : $vwB;
            $qty  = (string) number_format(($idx + 1) * 35, 4, '.', '');
            $this->postLine($ledger, $item, $loc, $vw, $qty, StockStatus::Available, $ref, null, null, "Bulk balance item {$item->sku}");
        }

        $this->command->info('StockDemoSeeder completed.');
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function postLine(
        StockLedger   $ledger,
        Item          $item,
        Location      $location,
        VirtualWarehouse $vw,
        string        $qty,
        StockStatus   $status,
        StockReference $ref,
        ?string       $batchNo,
        ?Carbon       $expiryDate,
        string        $description,
    ): void {
        try {
            $line = new StockLine(
                itemId: $item->id,
                locationId: $location->id,
                virtualWarehouseId: $vw->id,
                stockStatus: $status,
                enteredQty: $qty,
                enteredUomId: $item->base_uom_id,
                batchNo: $batchNo,
                expiryDate: $expiryDate,
            );

            $ledger->openingBalance([$line], "Opening balance demo: {$description}");

            $this->command->info("  Posted: {$description}");
        } catch (\Throwable $e) {
            $this->command->warn("  SKIPPED [{$description}]: {$e->getMessage()}");
        }
    }
}
