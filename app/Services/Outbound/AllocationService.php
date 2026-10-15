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

    public function allocate(DeliveryOrder $do): void
    {
        DB::transaction(function () use ($do) {
            $do->load('lines.item');

            foreach ($do->lines as $line) {
                if (in_array($line->status, [DoLineStatus::Allocated, DoLineStatus::Dispatched], true)) {
                    continue;
                }
                $this->allocateLine($line, $do);
            }

            // Determine overall DO status
            $lines        = $do->lines()->get();
            $allAllocated = $lines->every(fn ($l) => bccomp((string) $l->allocated_qty, (string) $l->requested_qty_base, 4) >= 0);
            $anyAllocated = $lines->some(fn ($l) => bccomp((string) $l->allocated_qty, '0', 4) > 0);
            $allowPartial = (bool) $this->settings->get('outbound.allow_partial_allocation', false);

            if ($allAllocated) {
                $do->status = DeliveryOrderStatus::Allocated;
            } elseif ($anyAllocated && $allowPartial) {
                $do->status = DeliveryOrderStatus::PartiallyAllocated;
                $this->handleBackorder($do);
            } else {
                // No stock at all — remain Approved
                $do->status = DeliveryOrderStatus::Approved;
            }

            $do->save();
        });
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

            $do->status = DeliveryOrderStatus::Approved;
            $do->save();
        });
    }

    public function reallocate(DeliveryOrder $do): void
    {
        $this->release($do);
        $this->allocate($do);
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

        $query = StockBalance::with(['batch', 'location'])
            ->where('virtual_warehouse_id', $do->virtual_warehouse_id)
            ->where('stock_status', 'available')
            ->whereHas('location', function ($q) {
                $q->where('type', 'storage')
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

    private function sortCandidates(Collection $balances, DeliveryOrder $do, DeliveryOrderLine $line): Collection
    {
        $useFefo = $do->allocation_rule === AllocationRule::Fefo
            || ($do->allocation_rule === AllocationRule::Default && optional($line->item)->track_expiry);

        return $balances->sortBy([
            function ($balance) use ($useFefo) {
                if ($useFefo && $balance->batch?->expiry_date) {
                    return $balance->batch->expiry_date->timestamp;
                }
                return $balance->batch?->created_at?->timestamp ?? PHP_INT_MAX;
            },
            fn ($balance) => $balance->created_at?->timestamp ?? PHP_INT_MAX,
            fn ($balance) => $balance->location?->pick_sequence ?? PHP_INT_MAX,
            fn ($balance) => $balance->location?->code ?? '',
        ]);
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
