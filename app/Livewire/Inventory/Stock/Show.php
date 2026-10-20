<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\Stock;

use App\Models\Dispatch;
use App\Models\Grn;
use App\Models\Inventory\SerialNumber;
use App\Models\PickTask;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use WithPagination;

    public StockBalance $stockBalance;

    public function mount(StockBalance $stockBalance): void
    {
        $this->stockBalance = $stockBalance->load([
            'item.category',
            'item.baseUom',
            'batch',
            'lpn',
            'location.warehouse',
            'virtualWarehouse',
            'reservations',
        ]);
    }

    #[Computed]
    public function allItemBalances()
    {
        return StockBalance::with(['batch', 'location.warehouse', 'virtualWarehouse'])
            ->where('item_id', $this->stockBalance->item_id)
            ->where('qty_on_hand', '>', 0)
            ->orderBy('location_id')
            ->get();
    }

    #[Computed]
    public function stockInfo(): array
    {
        $b = $this->stockBalance;

        // Totals across ALL locations/batches for this item
        $qtyOnHand    = (float) StockBalance::where('item_id', $b->item_id)->sum('qty_on_hand');
        $qtyAllocated = (float) StockBalance::where('item_id', $b->item_id)->sum('qty_allocated');
        $available    = $qtyOnHand - $qtyAllocated;

        // On-order qty: open PO lines for this item
        $onOrderQty = DB::table('purchase_order_lines')
            ->join('purchase_orders', 'purchase_order_lines.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_order_lines.item_id', $b->item_id)
            ->whereIn('purchase_orders.status', ['submitted', 'approved', 'receiving'])
            ->sum('purchase_order_lines.qty_base');

        // On-hold qty across all locations
        $damagedQty = (float) StockBalance::where('item_id', $b->item_id)
            ->where('stock_status', 'on_hold')
            ->sum('qty_on_hand');

        // Opening balance total for this item
        $openingBalance = (float) StockMovement::join('stock_transactions', 'stock_movements.stock_transaction_id', '=', 'stock_transactions.id')
            ->where('stock_movements.item_id', $b->item_id)
            ->where('stock_transactions.type', 'opening_balance')
            ->sum('stock_movements.qty');

        // Last known unit price
        $lastUnitPrice = DB::table('purchase_order_lines')
            ->where('item_id', $b->item_id)
            ->orderBy('id', 'desc')
            ->value('unit_price');
        $stockValue = $lastUnitPrice ? round($qtyOnHand * (float) $lastUnitPrice, 2) : null;

        // Last movement date for this item
        $lastMovement = StockMovement::join('stock_transactions', 'stock_movements.stock_transaction_id', '=', 'stock_transactions.id')
            ->where('stock_movements.item_id', $b->item_id)
            ->max('stock_transactions.posted_at');

        // Last cycle count date for this item
        $lastCount = DB::table('cycle_count_lines')
            ->where('item_id', $b->item_id)
            ->max('updated_at');

        // Serial count across all locations
        $serialCount = $b->item?->track_serial
            ? SerialNumber::where('item_id', $b->item_id)->count()
            : null;

        return compact(
            'qtyOnHand',
            'qtyAllocated',
            'available',
            'onOrderQty',
            'damagedQty',
            'openingBalance',
            'lastUnitPrice',
            'stockValue',
            'lastMovement',
            'lastCount',
            'serialCount',
        );
    }

    #[Computed]
    public function movements()
    {
        $b = $this->stockBalance;

        return StockMovement::with(['transaction.postedBy', 'batch', 'enteredUom'])
            ->join('stock_transactions', 'stock_movements.stock_transaction_id', '=', 'stock_transactions.id')
            ->select('stock_movements.*')
            ->where('stock_movements.item_id', $b->item_id)
            ->where('stock_movements.location_id', $b->location_id)
            ->where('stock_movements.virtual_warehouse_id', $b->virtual_warehouse_id)
            ->where('stock_movements.stock_status', $b->stock_status)
            ->when($b->batch_id !== null,
                fn ($q) => $q->where('stock_movements.batch_id', $b->batch_id),
                fn ($q) => $q->whereNull('stock_movements.batch_id')
            )
            ->when($b->lpn_id !== null,
                fn ($q) => $q->where('stock_movements.lpn_id', $b->lpn_id),
                fn ($q) => $q->whereNull('stock_movements.lpn_id')
            )
            ->orderBy('stock_transactions.posted_at', 'desc')
            ->paginate(20);
    }

    #[Computed]
    public function inboundHistory()
    {
        $b = $this->stockBalance;

        $paginator = StockMovement::with(['transaction.postedBy', 'batch', 'enteredUom'])
            ->join('stock_transactions', 'stock_movements.stock_transaction_id', '=', 'stock_transactions.id')
            ->select('stock_movements.*')
            ->where('stock_movements.item_id', $b->item_id)
            ->whereIn('stock_transactions.type', ['opening_balance', 'receipt', 'reversal'])
            ->when($b->batch_id !== null,
                fn ($q) => $q->where('stock_movements.batch_id', $b->batch_id),
                fn ($q) => $q->whereNull('stock_movements.batch_id')
            )
            ->when($b->lpn_id !== null,
                fn ($q) => $q->where('stock_movements.lpn_id', $b->lpn_id),
                fn ($q) => $q->whereNull('stock_movements.lpn_id')
            )
            ->orderBy('stock_transactions.posted_at', 'desc')
            ->paginate(15, ['*'], 'inboundPage');

        // Attach GRN (with supplier + warehouse) to each transaction on this page
        $grnIds = $paginator->getCollection()
            ->filter(fn ($m) => $m->transaction?->reference_type === 'grn' && $m->transaction?->reference_id)
            ->pluck('transaction.reference_id')
            ->unique()
            ->values();

        if ($grnIds->isNotEmpty()) {
            $grns = Grn::with(['supplierPartner', 'warehouse'])
                ->whereIn('id', $grnIds)
                ->get()
                ->keyBy('id');

            $paginator->getCollection()->each(function (StockMovement $m) use ($grns) {
                if ($m->transaction?->reference_type === 'grn' && $m->transaction?->reference_id) {
                    $m->transaction->setRelation('grn', $grns->get($m->transaction->reference_id));
                }
            });
        }

        return $paginator;
    }

    #[Computed]
    public function outboundHistory()
    {
        $b = $this->stockBalance;

        $paginator = StockMovement::with(['transaction.postedBy', 'batch', 'enteredUom'])
            ->join('stock_transactions', 'stock_movements.stock_transaction_id', '=', 'stock_transactions.id')
            ->select('stock_movements.*')
            ->where('stock_movements.item_id', $b->item_id)
            ->whereIn('stock_transactions.type', ['pick', 'dispatch'])
            ->when($b->batch_id !== null,
                fn ($q) => $q->where('stock_movements.batch_id', $b->batch_id),
                fn ($q) => $q->whereNull('stock_movements.batch_id')
            )
            ->when($b->lpn_id !== null,
                fn ($q) => $q->where('stock_movements.lpn_id', $b->lpn_id),
                fn ($q) => $q->whereNull('stock_movements.lpn_id')
            )
            ->orderBy('stock_transactions.posted_at', 'desc')
            ->paginate(15, ['*'], 'outboundPage');

        $items = $paginator->getCollection();

        // Attach Dispatch → DeliveryOrder (consignee + warehouse) for dispatch-type rows
        $dispatchIds = $items
            ->filter(fn ($m) => $m->transaction?->reference_type === 'dispatch' && $m->transaction?->reference_id)
            ->pluck('transaction.reference_id')->unique();

        if ($dispatchIds->isNotEmpty()) {
            $dispatches = Dispatch::with(['deliveryOrder.consigneePartner', 'deliveryOrder.warehouse'])
                ->whereIn('id', $dispatchIds)
                ->get()
                ->keyBy('id');

            $items->each(function (StockMovement $m) use ($dispatches) {
                if ($m->transaction?->reference_type === 'dispatch' && $m->transaction?->reference_id) {
                    $m->transaction->setRelation('dispatch', $dispatches->get($m->transaction->reference_id));
                }
            });
        }

        // Attach PickTask → PickList → DeliveryOrder (consignee + warehouse) for pick-type rows
        $pickTaskIds = $items
            ->filter(fn ($m) => $m->transaction?->reference_type === 'pick_task' && $m->transaction?->reference_id)
            ->pluck('transaction.reference_id')->unique();

        if ($pickTaskIds->isNotEmpty()) {
            $pickTasks = PickTask::with(['pickList.deliveryOrder.consigneePartner', 'pickList.deliveryOrder.warehouse'])
                ->whereIn('id', $pickTaskIds)
                ->get()
                ->keyBy('id');

            $items->each(function (StockMovement $m) use ($pickTasks) {
                if ($m->transaction?->reference_type === 'pick_task' && $m->transaction?->reference_id) {
                    $m->transaction->setRelation('pickTask', $pickTasks->get($m->transaction->reference_id));
                }
            });
        }

        return $paginator;
    }

    #[Computed]
    public function timeline(): Collection
    {
        $b = $this->stockBalance;

        $colorMap = [
            MovementType::OpeningBalance->value    => 'indigo',
            MovementType::Receipt->value           => 'green',
            MovementType::Putaway->value           => 'teal',
            MovementType::Relocation->value        => 'blue',
            MovementType::Pick->value              => 'orange',
            MovementType::Dispatch->value          => 'red',
            MovementType::OwnershipTransfer->value => 'purple',
            MovementType::Adjustment->value        => 'yellow',
            MovementType::CountAdjustment->value   => 'yellow',
            MovementType::Hold->value              => 'rose',
            MovementType::Release->value           => 'green',
            MovementType::Reversal->value          => 'red',
            MovementType::SerialCapture->value     => 'slate',
        ];

        return StockMovement::with(['transaction.postedBy'])
            ->join('stock_transactions', 'stock_movements.stock_transaction_id', '=', 'stock_transactions.id')
            ->select('stock_movements.*')
            ->where('stock_movements.item_id', $b->item_id)
            ->where('stock_movements.location_id', $b->location_id)
            ->where('stock_movements.virtual_warehouse_id', $b->virtual_warehouse_id)
            ->when($b->batch_id !== null,
                fn ($q) => $q->where('stock_movements.batch_id', $b->batch_id),
                fn ($q) => $q->whereNull('stock_movements.batch_id')
            )
            ->when($b->lpn_id !== null,
                fn ($q) => $q->where('stock_movements.lpn_id', $b->lpn_id),
                fn ($q) => $q->whereNull('stock_movements.lpn_id')
            )
            ->orderBy('stock_transactions.posted_at', 'desc')
            ->limit(60)
            ->get()
            ->map(fn (StockMovement $m) => [
                'type_label'     => $m->transaction->type->label(),
                'qty'            => (float) $m->qty,
                'posted_at'      => $m->transaction->posted_at,
                'posted_by'      => $m->transaction->postedBy?->name,
                'reference_no'   => $m->transaction->reference_no,
                'reference_type' => $m->transaction->reference_type,
                'notes'          => $m->transaction->notes,
                'color'          => $colorMap[$m->transaction->type->value] ?? 'slate',
            ]);
    }

    #[Computed]
    public function serials()
    {
        $b = $this->stockBalance;

        if (! $b->item?->track_serial) {
            return collect();
        }

        return SerialNumber::with(['location', 'batch'])
            ->where('item_id', $b->item_id)
            ->orderBy('location_id')
            ->orderBy('serial_no')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.inventory.stock.show')
            ->layout('components.layouts.app', [
                'title'           => 'Stock Detail',
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
