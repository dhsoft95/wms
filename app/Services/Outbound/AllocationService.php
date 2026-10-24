<?php

declare(strict_types=1);

namespace App\Services\Outbound;

use App\Enums\Outbound\AllocationRule;
use App\Enums\Outbound\AllocationStatus;
use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\DoLineStatus;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderAllocation;
use App\Models\DeliveryOrderLine;
use App\Models\Inventory\StockBalance;
use App\Services\Foundation\DocumentNumber;
use App\Services\Foundation\SettingsService;
use App\Services\Inventory\StockReservations;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AllocationService
{
    public function __construct(
        private readonly StockReservations $reservations,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return string[] Warning messages for lines that could not be fully allocated.
     */
    public function allocate(DeliveryOrder $do): array
    {
        $warnings = [];

        DB::transaction(function () use ($do, &$warnings) {
            $do->load('lines.item');

            if ($do->lines->isEmpty()) {
                throw new \DomainException('This delivery order has no lines and cannot be allocated.');
            }

            foreach ($do->lines as $line) {
                if (in_array($line->status, [DoLineStatus::Allocated, DoLineStatus::Dispatched], true)) {
                    continue;
                }
                $this->allocateLine($line, $do);
            }

            // Determine overall DO status
            $lines        = $do->lines()->get();
            $allAllocated = $lines->isNotEmpty() && $lines->every(fn ($l) => bccomp((string) $l->allocated_qty, (string) $l->requested_qty_base, 4) >= 0);
            $anyAllocated = $lines->some(fn ($l) => bccomp((string) $l->allocated_qty, '0', 4) > 0);
            $allowPartial = (bool) $this->settings->get('outbound.allow_partial_allocation', false);

            if ($allAllocated) {
                $do->status = DeliveryOrderStatus::Allocated;
            } elseif ($anyAllocated && $allowPartial) {
                $do->status = DeliveryOrderStatus::PartiallyAllocated;
                $this->handleBackorder($do);
            } elseif ($anyAllocated) {
                $do->status = DeliveryOrderStatus::PartiallyAllocated;
            } else {
                throw new \DomainException($this->buildAllocationError($do));
            }

            // Build per-line warnings for anything still unallocated
            if (! $allAllocated) {
                foreach ($lines->filter(fn ($l) => bccomp((string) $l->allocated_qty, (string) $l->requested_qty_base, 4) < 0) as $l) {
                    $warnings[] = $this->buildLineWarning($l, $do);
                }
            }

            $do->save();
        });

        return $warnings;
    }

    public function release(DeliveryOrder $do): void
    {
        DB::transaction(function () use ($do) {
            foreach ($do->lines()->with('allocations.stockReservation')->get() as $line) {
                foreach ($line->allocations()->where('status', AllocationStatus::Reserved->value)->get() as $allocation) {
                    if ($allocation->stockReservation) {
                        $this->reservations->release($allocation->stockReservation);
                    }
                    $allocation->delete();
                }
                $line->allocated_qty = '0.0000';
                $line->status        = DoLineStatus::Pending;
                $line->save();
            }

            // Revert to Acceptance (if it reached that step) otherwise Approved
            $do->status = $do->acceptance_at !== null
                ? DeliveryOrderStatus::Acceptance
                : DeliveryOrderStatus::Approved;
            $do->save();
        });
    }

    public function reallocate(DeliveryOrder $do): void
    {
        $this->release($do);
        $this->allocate($do);
    }

    /**
     * Resume picking for a Picked order that has lines not fully picked.
     *
     * Root-cause of the double-dispatch serial error:
     *   When executeTask() picks partial qty, the ledger deducts the picked amount
     *   from the reservation but leaves the remainder still holding qty_allocated on
     *   the source bin.  The old resumeFromShort only released *Reserved* allocations,
     *   so the bin showed net-available = 0, and getCandidates() fell back to staging
     *   (a pickable type), creating a staging→staging pick task.  Both tasks then had
     *   the same serials, causing a double-debit on dispatch.
     *
     * Fix:
     *   1. Release the reservation on *Picked* allocations too, freeing the bin's
     *      remaining qty_allocated so re-allocation finds the correct source location.
     *   2. After re-allocating, strip any new Reserved allocations that landed on the
     *      default staging location (picking FROM staging makes no sense—staging is
     *      only ever the pick destination / dispatch source).
     *
     * @return string[] Warning messages for lines still unallocatable.
     */
    public function resumeFromShort(DeliveryOrder $do): array
    {
        if ($do->status !== DeliveryOrderStatus::Picked) {
            throw new \DomainException('Can only resume picking on a Picked order.');
        }

        $stagingId = $do->warehouse?->defaultStagingLocation?->id;
        $warnings  = [];

        DB::transaction(function () use ($do, $stagingId, &$warnings) {
            $hasUnmet = false;

            foreach ($do->lines()->with('allocations.stockReservation')->get() as $line) {
                $owed = bcsub((string) $line->requested_qty_base, (string) $line->picked_qty, 4);

                if (bccomp($owed, '0', 4) <= 0) {
                    continue; // fully picked — nothing to do
                }

                $hasUnmet = true;

                // Release reservations on ALL allocations for this line — both Reserved
                // and Picked — so the source bin's qty_allocated is fully freed and
                // re-allocation can find the remaining physical stock there.
                foreach ($line->allocations as $alloc) {
                    if ($alloc->stockReservation) {
                        $this->reservations->release($alloc->stockReservation);
                    }
                    // Remove Reserved allocations; keep Picked ones (they record history)
                    if ($alloc->status === AllocationStatus::Reserved) {
                        $alloc->delete();
                    }
                }

                // Reset line so allocateLine() targets exactly the unmet qty
                $line->allocated_qty = $line->picked_qty;
                $line->short_qty     = '0.0000';
                $line->status        = DoLineStatus::Pending;
                $line->save();
            }

            if (! $hasUnmet) {
                throw new \DomainException('All lines are fully picked — nothing to resume.');
            }

            // Re-allocate from bin/storage/hazardous locations
            $warnings = $this->allocate($do);

            // Safety net: strip any new Reserved allocations that landed on staging.
            // If the allocator still picked staging (e.g. FEFO ordering), remove those
            // to prevent a staging→staging pick task that double-counts serials on dispatch.
            if ($stagingId !== null) {
                $removedQty = false;
                foreach ($do->lines()->with('allocations.stockReservation')->get() as $line) {
                    foreach ($line->allocations()
                                 ->where('status', AllocationStatus::Reserved->value)
                                 ->where('location_id', $stagingId)
                                 ->with('stockReservation')
                                 ->get() as $alloc) {
                        if ($alloc->stockReservation) {
                            $this->reservations->release($alloc->stockReservation);
                        }
                        $line->allocated_qty = bcsub((string) $line->allocated_qty, (string) $alloc->qty, 4);
                        $alloc->delete();
                        $removedQty = true;
                    }
                    if ($removedQty) {
                        $line->status = bccomp(
                            (string) $line->allocated_qty,
                            (string) $line->requested_qty_base, 4
                        ) >= 0 ? DoLineStatus::Allocated : DoLineStatus::PartiallyAllocated;
                        $line->save();
                    }
                }

                if ($removedQty) {
                    // Recalculate DO status after staging allocations were stripped
                    $lines        = $do->lines()->get();
                    $allAllocated = $lines->isNotEmpty() && $lines->every(fn ($l) => bccomp((string) $l->allocated_qty, (string) $l->requested_qty_base, 4) >= 0);
                    $do->status   = $allAllocated
                        ? DeliveryOrderStatus::Allocated
                        : DeliveryOrderStatus::PartiallyAllocated;
                    $do->save();

                    $warnings[] = 'Some remaining stock was in staging (already picked for this order) and was excluded — check source bin availability.';
                }
            }
        });

        return $warnings;
    }

    public function allocateLine(DeliveryOrderLine $line, DeliveryOrder $do): void
    {
        $candidates = $this->getCandidates($line, $do);
        $sorted     = $this->sortCandidates($candidates, $do, $line);

        $remaining = bcsub((string) $line->requested_qty_base, (string) $line->allocated_qty, 4);

        foreach ($sorted as $balance) {
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }

            $available = bcsub((string) $balance->qty_on_hand, (string) $balance->qty_allocated, 4);
            if (bccomp($available, '0', 4) <= 0) {
                continue;
            }

            $allocateQty = bccomp($available, $remaining, 4) <= 0 ? $available : $remaining;

            $reservation = $this->reservations->reserve(
                $balance,
                $allocateQty,
                'delivery_order_line',
                $line->id
            );

            DeliveryOrderAllocation::create([
                'delivery_order_line_id' => $line->id,
                'stock_balance_id'       => $balance->id,
                'stock_reservation_id'   => $reservation->id,
                'item_id'                => $balance->item_id,
                'batch_id'               => $balance->batch_id,
                'lpn_id'                 => $balance->lpn_id,
                'location_id'            => $balance->location_id,
                'qty'                    => $allocateQty,
                'status'                 => AllocationStatus::Reserved,
            ]);

            $line->allocated_qty = bcadd((string) $line->allocated_qty, $allocateQty, 4);
            $remaining = bcsub($remaining, $allocateQty, 4);
        }

        if (bccomp((string) $line->allocated_qty, (string) $line->requested_qty_base, 4) >= 0) {
            $line->status = DoLineStatus::Allocated;
        } elseif (bccomp((string) $line->allocated_qty, '0', 4) > 0) {
            $line->status = DoLineStatus::PartiallyAllocated;
        }

        $line->save();
    }

    private function getCandidates(DeliveryOrderLine $line, DeliveryOrder $do): Collection
    {
        $today    = now()->toDateString();
        $minShelfLife = (int) $this->settings->get('outbound.min_shelf_life_days', 0);
        $minExpiry    = $minShelfLife > 0 ? now()->addDays($minShelfLife)->toDateString() : null;

        // Pickable location types: storage, staging, hazardous
        $pickableTypes = ['storage', 'staging', 'hazardous'];

        $query = StockBalance::with(['batch', 'location'])
            ->where('virtual_warehouse_id', $do->virtual_warehouse_id)
            ->where('stock_status', 'available')
            ->whereHas('location', function ($q) use ($pickableTypes) {
                $q->whereIn('type', $pickableTypes)
                  ->whereIn('status', ['active', 'full']);
            })
            ->whereRaw('qty_on_hand - qty_allocated > 0');

        if ($line->requested_batch_id) {
            $query->where('batch_id', $line->requested_batch_id);
        } else {
            $query->where('item_id', $line->item_id);
        }

        // Exclude expired stock
        $query->where(function ($q) use ($today) {
            $q->whereNull('batch_id')
              ->orWhereHas('batch', function ($bq) use ($today) {
                  $bq->where(function ($inner) use ($today) {
                      $inner->whereNull('expiry_date')
                            ->orWhere('expiry_date', '>', $today);
                  });
              });
        });

        // Exclude stock expiring within min shelf life
        if ($minExpiry !== null) {
            $query->where(function ($q) use ($minExpiry) {
                $q->whereNull('batch_id')
                  ->orWhereHas('batch', function ($bq) use ($minExpiry) {
                      $bq->where(function ($inner) use ($minExpiry) {
                          $inner->whereNull('expiry_date')
                                ->orWhere('expiry_date', '>=', $minExpiry);
                      });
                  });
            });
        }

        return $query->get();
    }

    private function buildAllocationError(DeliveryOrder $do): string
    {
        $vwId        = $do->virtual_warehouse_id;
        $itemIds     = $do->lines->pluck('item_id')->filter()->unique()->values();
        $pickable    = ['storage', 'staging', 'hazardous'];
        $nonPickable = ['receiving_dock', 'yard_slot', 'quarantine', 'transit', 'disposal'];

        if ($itemIds->isEmpty()) {
            return 'This delivery order has no lines.';
        }

        // Check 1: any stock for these items at all in this virtual warehouse?
        $anyStock = StockBalance::whereIn('item_id', $itemIds)
            ->where('virtual_warehouse_id', $vwId)
            ->where('qty_on_hand', '>', 0)
            ->exists();

        if (! $anyStock) {
            return 'No stock found for the requested items in this virtual warehouse. Ensure items have been received against a PO assigned to the same virtual warehouse.';
        }

        // Check 2: is the stock still in dock/quarantine (putaway not yet done)?
        $inDock = StockBalance::whereIn('item_id', $itemIds)
            ->where('virtual_warehouse_id', $vwId)
            ->whereRaw('qty_on_hand - qty_allocated > 0')
            ->whereHas('location', fn ($q) => $q->whereIn('type', $nonPickable))
            ->exists();

        if ($inDock) {
            return 'Stock has been received but putaway is not yet complete. Complete the putaway tasks to move stock into storage bins before allocating.';
        }

        // Check 3: stock in pickable location but wrong status?
        $wrongStatus = StockBalance::whereIn('item_id', $itemIds)
            ->where('virtual_warehouse_id', $vwId)
            ->whereRaw('qty_on_hand - qty_allocated > 0')
            ->whereHas('location', fn ($q) => $q->whereIn('type', $pickable))
            ->where('stock_status', '!=', 'available')
            ->exists();

        if ($wrongStatus) {
            return 'Stock is on hold or obsolete and cannot be allocated. Change the stock status to Available first.';
        }

        // Check 4: location blocked?
        $blockedLoc = StockBalance::whereIn('item_id', $itemIds)
            ->where('virtual_warehouse_id', $vwId)
            ->where('stock_status', 'available')
            ->whereRaw('qty_on_hand - qty_allocated > 0')
            ->whereHas('location', fn ($q) => $q->whereIn('type', $pickable)->whereNotIn('status', ['active', 'full']))
            ->exists();

        if ($blockedLoc) {
            return 'Stock is in blocked locations. Unblock the locations or relocate the stock before allocating.';
        }

        // Check 5: all qty already reserved?
        $fullyAllocated = StockBalance::whereIn('item_id', $itemIds)
            ->where('virtual_warehouse_id', $vwId)
            ->where('stock_status', 'available')
            ->whereHas('location', fn ($q) => $q->whereIn('type', $pickable)->whereIn('status', ['active', 'full']))
            ->whereRaw('qty_on_hand - qty_allocated <= 0')
            ->exists();

        if ($fullyAllocated) {
            return 'All available stock is already allocated to other orders.';
        }

        return 'No stock could be allocated. Ensure stock has been received and put away into storage locations for this virtual warehouse.';
    }

    private function buildLineWarning(DeliveryOrderLine $line, DeliveryOrder $do): string
    {
        $sku      = $line->item?->sku ?? "Line {$line->line_no}";
        $vwId     = $do->virtual_warehouse_id;
        $pickable = ['storage', 'staging', 'hazardous'];

        $needed    = bcsub((string) $line->requested_qty_base, (string) $line->allocated_qty, 4);
        $allocated = bccomp((string) $line->allocated_qty, '0', 4) > 0;

        $anyStock = StockBalance::where('item_id', $line->item_id)
            ->where('virtual_warehouse_id', $vwId)
            ->where('qty_on_hand', '>', 0)
            ->exists();

        if (! $anyStock) {
            return "{$sku}: no stock in this virtual warehouse.";
        }

        $inDock = StockBalance::where('item_id', $line->item_id)
            ->where('virtual_warehouse_id', $vwId)
            ->whereRaw('qty_on_hand - qty_allocated > 0')
            ->whereHas('location', fn ($q) => $q->whereNotIn('type', $pickable))
            ->exists();

        if ($inDock) {
            return "{$sku}: putaway not complete — stock is still in the receiving dock.";
        }

        $wrongStatus = StockBalance::where('item_id', $line->item_id)
            ->where('virtual_warehouse_id', $vwId)
            ->whereRaw('qty_on_hand - qty_allocated > 0')
            ->whereHas('location', fn ($q) => $q->whereIn('type', $pickable))
            ->where('stock_status', '!=', 'available')
            ->exists();

        if ($wrongStatus) {
            return "{$sku}: stock is on hold or obsolete.";
        }

        $insufficientQty = StockBalance::where('item_id', $line->item_id)
            ->where('virtual_warehouse_id', $vwId)
            ->where('stock_status', 'available')
            ->whereHas('location', fn ($q) => $q->whereIn('type', $pickable)->whereIn('status', ['active', 'full']))
            ->whereRaw('qty_on_hand - qty_allocated > 0')
            ->exists();

        if ($insufficientQty && $allocated) {
            return "{$sku}: only partially allocated — insufficient stock (short by {$needed} " . ($line->item?->baseUom?->code ?? 'units') . ').';
        }

        if ($insufficientQty) {
            return "{$sku}: insufficient available stock.";
        }

        return "{$sku}: could not be allocated (short by {$needed}).";
    }

    private function sortCandidates(Collection $balances, DeliveryOrder $do, DeliveryOrderLine $line): Collection
    {
        // Resolve rules: item rules first, fall back to global default as single rule.
        $itemRules = $line->item?->allocation_rules ?? [];
        if (empty($itemRules)) {
            $global    = $this->settings->get('operations.allocation_rule', 'fifo');
            $itemRules = [$global];
        }

        // Build comparator list from the ordered rules array.
        $comparators = [];
        foreach ($itemRules as $rule) {
            $comparators[] = match ($rule) {
                'lifo' => fn ($a, $b) => ($b->received_at?->timestamp ?? 0) <=> ($a->received_at?->timestamp ?? 0),
                'fefo' => function ($a, $b) {
                    $aExp = $a->batch?->expiry_date?->timestamp;
                    $bExp = $b->batch?->expiry_date?->timestamp;
                    if ($aExp === null && $bExp === null) return 0;
                    if ($aExp === null) return 1;   // nulls last
                    if ($bExp === null) return -1;
                    return $aExp <=> $bExp;
                },
                default => function ($a, $b) {        // fifo
                    $aRec = $a->received_at?->timestamp ?? PHP_INT_MAX;
                    $bRec = $b->received_at?->timestamp ?? PHP_INT_MAX;
                    return $aRec <=> $bRec;
                },
            };
        }

        // Tiebreaker: pick_sequence then location code
        $comparators[] = fn ($a, $b) => ($a->location?->pick_sequence ?? PHP_INT_MAX) <=> ($b->location?->pick_sequence ?? PHP_INT_MAX);
        $comparators[] = fn ($a, $b) => ($a->location?->code ?? '') <=> ($b->location?->code ?? '');

        return $balances->sort(function ($a, $b) use ($comparators) {
            foreach ($comparators as $cmp) {
                $result = $cmp($a, $b);
                if ($result !== 0) {
                    return $result;
                }
            }
            return 0;
        })->values();
    }

    private function handleBackorder(DeliveryOrder $do): void
    {
        if (! (bool) $this->settings->get('outbound.auto_backorder', false)) {
            return;
        }

        // Create backorder DO for unallocated remainder
        $hasRemainder = false;
        foreach ($do->lines as $line) {
            $remainder = bcsub((string) $line->requested_qty_base, (string) $line->allocated_qty, 4);
            if (bccomp($remainder, '0', 4) > 0) {
                $hasRemainder = true;
                break;
            }
        }

        if (! $hasRemainder) {
            return;
        }

        $backorder = DeliveryOrder::create([
            'number'               => DocumentNumber::next('DO'),
            'virtual_warehouse_id' => $do->virtual_warehouse_id,
            'warehouse_id'         => $do->warehouse_id,
            'department_id'        => $do->department_id,
            'consignee_partner_id' => $do->consignee_partner_id,
            'delivery_address'     => $do->delivery_address,
            'required_date'        => $do->required_date,
            'priority'             => $do->priority,
            'allocation_rule'      => $do->allocation_rule,
            'status'               => DeliveryOrderStatus::Approved,
            'requested_by'         => $do->requested_by,
            'remarks'              => "Backorder of {$do->number}",
            'backorder_of_id'      => $do->id,
        ]);

        foreach ($do->lines as $line) {
            $remainder = bcsub((string) $line->requested_qty_base, (string) $line->allocated_qty, 4);
            if (bccomp($remainder, '0', 4) <= 0) {
                continue;
            }

            \App\Models\DeliveryOrderLine::create([
                'delivery_order_id'  => $backorder->id,
                'line_no'            => $line->line_no,
                'item_id'            => $line->item_id,
                'uom_id'             => $line->uom_id,
                'requested_qty'      => $line->requested_qty,
                'requested_qty_base' => $remainder,
                'requested_batch_id' => $line->requested_batch_id,
                'status'             => DoLineStatus::Pending,
            ]);
        }
    }
}
