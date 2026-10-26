<?php

declare(strict_types=1);

namespace App\Livewire\Outbound\Orders;

use App\Enums\Outbound\DeliveryOrderStatus;
use App\Enums\Outbound\DeliveryOrderPriority;
use App\Models\DeliveryOrder;
use App\Services\Outbound\AllocationService;
use App\Services\Outbound\DeliveryOrderWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string  $statusFilter   = 'all';
    public string  $search         = '';
    public string  $priorityFilter = '';
    public string  $dateFrom       = '';
    public string  $dateTo         = '';
    public bool    $overdueOnly    = false;

    // Modal state
    public bool    $showRejectModal  = false;
    public ?int    $actionDoId       = null;
    public string  $rejectNotes      = '';

    public function updatedSearch(): void        { $this->resetPage(); }
    public function updatedStatusFilter(): void  { $this->resetPage(); }
    public function updatedPriorityFilter(): void{ $this->resetPage(); }
    public function updatedOverdueOnly(): void   { $this->resetPage(); }

    #[Computed]
    public function orders(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $this->baseQuery()
            ->with(['virtualWarehouse', 'department', 'consigneePartner', 'requestedBy', 'warehouse'])
            ->withCount('lines');

        $isRequester = auth()->user()->hasRole('department-user');
        $blockedStatus = DeliveryOrderStatus::ReturnedForCorrection->value;

        if ($this->statusFilter !== 'all' && ! ($isRequester && $this->statusFilter === $blockedStatus)) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search !== '') {
            $s = '%' . $this->search . '%';
            $query->where(function (Builder $q) use ($s) {
                $q->where('number', 'like', $s)
                  ->orWhereHas('consigneePartner', fn ($sq) => $sq->where('name', 'like', $s))
                  ->orWhereHas('virtualWarehouse', fn ($sq) => $sq->where('name', 'like', $s));
            });
        }

        if ($this->priorityFilter !== '') {
            $query->where('priority', $this->priorityFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('required_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('required_date', '<=', $this->dateTo);
        }

        if ($this->overdueOnly) {
            $query->whereDate('required_date', '<', now())
                  ->whereNotIn('status', [
                      DeliveryOrderStatus::Dispatched->value,
                      DeliveryOrderStatus::Delivered->value,
                      DeliveryOrderStatus::Cancelled->value,
                  ]);
        }

        return $query->orderByDesc('created_at')->paginate(20);
    }

    #[Computed]
    public function statusCounts(): array
    {
        $base   = $this->baseQuery();
        $counts = ['all' => (clone $base)->count()];

        foreach (DeliveryOrderStatus::cases() as $status) {
            $counts[$status->value] = (clone $base)->where('status', $status->value)->count();
        }

        return $counts;
    }

    #[Computed]
    public function statuses(): array
    {
        return DeliveryOrderStatus::cases();
    }

    #[Computed]
    public function priorities(): array
    {
        return DeliveryOrderPriority::cases();
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function submitOrder(int $id): void
    {
        $do = DeliveryOrder::findOrFail($id);
        $this->authorize('submit', $do);

        app(DeliveryOrderWorkflow::class)->submit($do, auth()->user());

        $this->dispatch('toast', message: "Order {$do->number} submitted.", type: 'success');
        $this->resetPage();
    }

    public function allocateOrder(int $id): void
    {
        $do = DeliveryOrder::findOrFail($id);
        $this->authorize('allocate', $do);

        app(AllocationService::class)->allocate($do);

        $this->dispatch('toast', message: "Order {$do->number} allocated.", type: 'success');
        $this->resetPage();
    }

    public function openRejectModal(int $id): void
    {
        $this->actionDoId   = $id;
        $this->rejectNotes  = '';
        $this->showRejectModal = true;
    }

    public function confirmReject(): void
    {
        $this->validate(['rejectNotes' => ['required', 'string', 'min:5']]);

        $do = DeliveryOrder::findOrFail($this->actionDoId);
        $this->authorize('cancel', $do);

        app(DeliveryOrderWorkflow::class)->reject($do, auth()->user(), $this->rejectNotes);

        $this->showRejectModal = false;
        $this->actionDoId      = null;
        $this->rejectNotes     = '';

        $this->dispatch('toast', message: "Order {$do->number} rejected.", type: 'success');
        $this->resetPage();
    }

    public function cancelOrder(int $id): void
    {
        $do = DeliveryOrder::findOrFail($id);
        $this->authorize('cancel', $do);

        app(DeliveryOrderWorkflow::class)->cancel($do, auth()->user());

        $this->dispatch('toast', message: "Order {$do->number} cancelled.", type: 'success');
        $this->resetPage();
    }

    public ?int $deleteOrderId = null;

    public function deleteOrder(int $id): void
    {
        $do = DeliveryOrder::findOrFail($id);
        $this->authorize('delete', $do);
        try {
            app(DeliveryOrderWorkflow::class)->adminDelete($do, auth()->user());
            $this->deleteOrderId = null;
            $this->dispatch('toast', message: "Order {$do->number} deleted.", type: 'success');
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->deleteOrderId = null;
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function baseQuery(): Builder
    {
        $query = DeliveryOrder::query();

        $user = auth()->user();
        if (! $user->canAccessAllVirtualWarehouses()) {
            $vwIds = $user->accessibleVirtualWarehouseIds();
            if (! empty($vwIds)) {
                $query->whereIn('virtual_warehouse_id', $vwIds);
            }
        }

        if (! $user->hasAnyRole(['warehouse-manager', 'system-admin'])) {
            $query->where(function ($q) use ($user) {
                $q->where('status', '!=', DeliveryOrderStatus::ReturnedForCorrection->value)
                  ->orWhereHas('department', fn ($dq) => $dq->where('head_user_id', $user->id));
            });
        }

        return $query;
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.outbound.orders.index')
            ->layout('components.layouts.app', [
                'title'           => 'Delivery Orders',
                'breadcrumbGroup' => 'Outbound',
            ]);
    }
}
