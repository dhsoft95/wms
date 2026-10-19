<?php

declare(strict_types=1);

namespace App\Livewire\Orders;

use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $tab = 'all'; // all | po | mrn | transfer

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public bool   $showTypeModal      = false;
    public bool   $showTransferStep   = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTab(): void
    {
        $this->resetPage();
        $this->statusFilter = '';
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        $user           = auth()->user();
        $isUnrestricted = $user->hasAnyRole(config('wms.vw_unrestricted_roles', [
            'system-admin', 'warehouse-manager', 'viewer-auditor', 'storekeeper', 'scanner-operator',
        ]));

        // Accessible VW IDs for restricted users
        $accessibleVwIds = $isUnrestricted ? [] : $user->accessibleVirtualWarehouseIds();

        // ── PO query ──────────────────────────────────────────────────────────
        $poQ = DB::table('purchase_orders')
            ->leftJoin('virtual_warehouses as povw', 'purchase_orders.virtual_warehouse_id', '=', 'povw.id')
            ->select(
                DB::raw("'po' as rec_type"),
                'purchase_orders.id', 'purchase_orders.number',
                'purchase_orders.virtual_warehouse_id',
                'purchase_orders.warehouse_id',
                DB::raw('povw.name as department_name'),
                DB::raw('expected_date as doc_date'),
                'purchase_orders.status', 'purchase_orders.created_at'
            )
            ->when($this->tab !== 'all' && $this->tab !== 'po', fn ($q) => $q->whereRaw('1=0'))
            ->when($this->search, fn ($q) => $q->where('purchase_orders.number', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('purchase_orders.status', $this->statusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('purchase_orders.created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('purchase_orders.created_at', '<=', $this->dateTo))
            ->when(! $isUnrestricted, function ($q) use ($user) {
                $whIds = \App\Models\ItemCategoryUser::where('user_id', $user->id)
                    ->pluck('warehouse_id')->unique()->values()->all();
                if (! empty($whIds)) {
                    $q->whereIn('purchase_orders.warehouse_id', $whIds);
                } else {
                    $q->whereRaw('1=0');
                }
            });

        // ── DO / MRN query ────────────────────────────────────────────────────
        $doQ = DB::table('delivery_orders')
            ->join('virtual_warehouses', 'delivery_orders.virtual_warehouse_id', '=', 'virtual_warehouses.id')
            ->leftJoin('departments', 'delivery_orders.department_id', '=', 'departments.id')
            ->select(
                DB::raw("'mrn' as rec_type"),
                'delivery_orders.id', 'delivery_orders.number',
                'delivery_orders.virtual_warehouse_id',
                'delivery_orders.warehouse_id',
                'departments.name as department_name',
                DB::raw('delivery_orders.required_date as doc_date'),
                'delivery_orders.status', 'delivery_orders.created_at'
            )
            ->when($this->tab !== 'all' && $this->tab !== 'mrn', fn ($q) => $q->whereRaw('1=0'))
            ->when($this->search, fn ($q) => $q->where('delivery_orders.number', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('delivery_orders.status', $this->statusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('delivery_orders.created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('delivery_orders.created_at', '<=', $this->dateTo))
            ->when(! $isUnrestricted && ! empty($accessibleVwIds), fn ($q) => $q->whereIn('delivery_orders.virtual_warehouse_id', $accessibleVwIds))
            ->when(! $isUnrestricted && empty($accessibleVwIds), fn ($q) => $q->whereRaw('1=0'));

        // ── In-warehouse movement (MOV) query ────────────────────────────────
        $movQ = DB::table('internal_movements')
            ->leftJoin('virtual_warehouses as mvw', 'internal_movements.virtual_warehouse_id', '=', 'mvw.id')
            ->select(
                DB::raw("'mov' as rec_type"),
                'internal_movements.id', 'internal_movements.number',
                'internal_movements.virtual_warehouse_id',
                DB::raw('NULL as warehouse_id'),
                DB::raw('mvw.name as department_name'),
                DB::raw('internal_movements.created_at as doc_date'),
                'internal_movements.status', 'internal_movements.created_at'
            )
            ->whereNull('internal_movements.deleted_at')
            ->when($this->tab !== 'all' && $this->tab !== 'transfer', fn ($q) => $q->whereRaw('1=0'))
            ->when($this->search, fn ($q) => $q->where('internal_movements.number', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('internal_movements.status', $this->statusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('internal_movements.created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('internal_movements.created_at', '<=', $this->dateTo))
            ->when(! $isUnrestricted && ! empty($accessibleVwIds), fn ($q) => $q->whereIn('internal_movements.virtual_warehouse_id', $accessibleVwIds))
            ->when(! $isUnrestricted && empty($accessibleVwIds), fn ($q) => $q->whereRaw('1=0'));

        // ── Departmental transfer (TRF) query ─────────────────────────────────
        $trQ = DB::table('stock_transfers')
            ->leftJoin('virtual_warehouses as fvw', 'stock_transfers.from_virtual_warehouse_id', '=', 'fvw.id')
            ->select(
                DB::raw("'trf' as rec_type"),
                'stock_transfers.id', 'stock_transfers.number',
                'stock_transfers.from_virtual_warehouse_id as virtual_warehouse_id',
                DB::raw('NULL as warehouse_id'),
                DB::raw('fvw.name as department_name'),
                DB::raw('stock_transfers.created_at as doc_date'),
                'stock_transfers.status', 'stock_transfers.created_at'
            )
            ->when($this->tab !== 'all' && $this->tab !== 'transfer', fn ($q) => $q->whereRaw('1=0'))
            ->when($this->search, fn ($q) => $q->where('stock_transfers.number', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('stock_transfers.status', $this->statusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('stock_transfers.created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('stock_transfers.created_at', '<=', $this->dateTo))
            ->when(! $isUnrestricted && ! empty($accessibleVwIds), fn ($q) => $q->where(fn ($q2) =>
                $q2->whereIn('stock_transfers.from_virtual_warehouse_id', $accessibleVwIds)
                   ->orWhereIn('stock_transfers.to_virtual_warehouse_id', $accessibleVwIds)
            ))
            ->when(! $isUnrestricted && empty($accessibleVwIds), fn ($q) => $q->whereRaw('1=0'));

        // ── Warehouse transfer (WTR) query ────────────────────────────────────
        $wtrQ = DB::table('warehouse_transfers')
            ->leftJoin('virtual_warehouses as fvw2', 'warehouse_transfers.from_virtual_warehouse_id', '=', 'fvw2.id')
            ->select(
                DB::raw("'wtr' as rec_type"),
                'warehouse_transfers.id', 'warehouse_transfers.number',
                'warehouse_transfers.from_virtual_warehouse_id as virtual_warehouse_id',
                'warehouse_transfers.from_warehouse_id as warehouse_id',
                DB::raw('fvw2.name as department_name'),
                DB::raw('warehouse_transfers.created_at as doc_date'),
                'warehouse_transfers.status', 'warehouse_transfers.created_at'
            )
            ->whereNull('warehouse_transfers.deleted_at')
            ->when($this->tab !== 'all' && $this->tab !== 'transfer', fn ($q) => $q->whereRaw('1=0'))
            ->when($this->search, fn ($q) => $q->where('warehouse_transfers.number', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('warehouse_transfers.status', $this->statusFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('warehouse_transfers.created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('warehouse_transfers.created_at', '<=', $this->dateTo))
            ->when(! $isUnrestricted && ! empty($accessibleVwIds), fn ($q) => $q->where(fn ($q2) =>
                $q2->whereIn('warehouse_transfers.from_virtual_warehouse_id', $accessibleVwIds)
                   ->orWhereIn('warehouse_transfers.to_virtual_warehouse_id', $accessibleVwIds)
            ))
            ->when(! $isUnrestricted && empty($accessibleVwIds), fn ($q) => $q->whereRaw('1=0'));

        // ── Union and paginate ────────────────────────────────────────────────
        $union = $poQ->union($doQ)->union($movQ)->union($trQ)->union($wtrQ);

        $perPage = 20;
        $page    = $this->getPage();

        $total = DB::query()->fromSub($union, 'orders_union')->count();
        $items = DB::query()->fromSub($union, 'orders_union')
            ->orderBy('created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    #[Computed]
    public function canCreateAny(): bool
    {
        $user = auth()->user();

        return $user->can('po.create') || $user->can('orders.create') || $user->can('transfers.create');
    }

    public function render(): View
    {
        return view('livewire.orders.index')
            ->layout('components.layouts.app', [
                'title'           => 'Orders',
                'breadcrumbGroup' => null,
            ]);
    }
}
