<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\LocationLevel;
use App\Enums\LocationStatus;
use App\Models\Inventory\Batch;
use App\Models\Item;
use App\Models\Location;
use App\Models\VirtualWarehouse;
use Carbon\Carbon;

/**
 * Canonical validation rules for inbound opening-stock lines.
 * Used by both OpeningStockImport and StockLedger so they can never disagree.
 */
final class StockLineValidator
{
    /**
     * Validate an inbound stock line against the shared ruleset.
     *
     * @param  Batch|null  $existingBatch  The batch record already in the DB (not a newly-created one).
     * @param  string      $qty            Positive quantity string (base units).
     * @return array       Empty on success; array of ['column' => …, 'message' => …] on failure.
     */
    public static function check(
        Item             $item,
        Location         $location,
        VirtualWarehouse $vw,
        ?Batch           $existingBatch,
        ?string          $batchNo,
        ?string          $expiryDate,
        string           $qty,
    ): array {
        $errors = [];

        if (! $item->is_active) {
            $errors[] = ['column' => 'sku', 'message' => "Item '{$item->sku}' is inactive."];
        }

        if ($location->level !== LocationLevel::Bin) {
            $errors[] = ['column' => 'location_code', 'message' => "Location '{$location->code}' is a {$location->level->label()}, not a bin. Choose a bin location."];
        }

        if ($location->status === LocationStatus::Blocked) {
            $errors[] = ['column' => 'location_code', 'message' => "Location '{$location->code}' is blocked."];
        }

        if (! $vw->is_active) {
            $errors[] = ['column' => 'vw_code', 'message' => "Virtual warehouse '{$vw->code}' is inactive."];
        }

        if (! is_numeric($qty) || (float) $qty <= 0) {
            $errors[] = ['column' => 'qty', 'message' => "'qty' must be a positive number."];
        }

        if ($item->is_active) {
            if ($item->track_batch && ($batchNo === null || $batchNo === '')) {
                $errors[] = ['column' => 'batch_no', 'message' => "Item '{$item->sku}' requires a batch number."];
            }

            if ($item->track_expiry && ($expiryDate === null || $expiryDate === '')) {
                $errors[] = ['column' => 'expiry_date', 'message' => "Item '{$item->sku}' requires an expiry date."];
            }

            if (
                $existingBatch !== null
                && $expiryDate !== null
                && $expiryDate !== ''
                && $existingBatch->expiry_date !== null
                && $existingBatch->expiry_date->toDateString() !== Carbon::parse($expiryDate)->toDateString()
            ) {
                $errors[] = [
                    'column'  => 'expiry_date',
                    'message' => "Batch '{$batchNo}' already has expiry {$existingBatch->expiry_date->toDateString()}, cannot set to {$expiryDate}.",
                ];
            }
        }

        return $errors;
    }

    /**
     * Same rules as check() but throws \InvalidArgumentException on the first error.
     * Used by StockLedger::processLine() to keep validation in sync with the import.
     */
    public static function assert(
        Item             $item,
        Location         $location,
        VirtualWarehouse $vw,
        ?Batch           $existingBatch,
        ?string          $batchNo,
        ?string          $expiryDate,
        string           $qty,
    ): void {
        $errors = self::check($item, $location, $vw, $existingBatch, $batchNo, $expiryDate, $qty);
        if (! empty($errors)) {
            throw new \InvalidArgumentException($errors[0]['message']);
        }
    }
}
