<?php

namespace App\Livewire\Inventory\Stock;

use App\Enums\Inventory\MovementType;
use App\Models\VirtualWarehouse;
use App\Services\Inventory\StockLedger;
use App\Services\Inventory\StockQueryService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Movements extends Component
{
    use WithPagination;

    public string $dateFrom = '';
    public string $dateTo = '';
    public string $filterType = '';
    public string $filterItem = '';
    public string $filterLocation = '';
    public string $filterVw = '';
    public string $filterRefNo = '';
    public string $filterUser = '';
    public ?int $viewingTransactionId = null;

    public string $reverseReason = '';
    public ?int $reversingId = null;

    public function updatingDateFrom(): void { $this->resetPage(); }
    public function updatingDateTo(): void { $this->resetPage(); }
    public function updatingFilterType(): void { $this->resetPage(); }
    public function updatingFilterItem(): void { $this->resetPage(); }
    public function updatingFilterLocation(): void { $this->resetPage(); }
    public function updatingFilterVw(): void { $this->resetPage(); }
    public function updatingFilterRefNo(): void { $this->resetPage(); }
    public function updatingFilterUser(): void { $this->resetPage(); }

    public function viewTransaction(int $id): void
    {
        $this->viewingTransactionId = $id;
    }

    public function closeDrawer(): void
    {
        $this->viewingTransactionId = null;
    }

    public function openReverseModal(int $id): void
    {
        $this->reversingId = $id;
        $this->reverseReason = '';
    }

    public function closeReverseModal(): void
    {
        $this->reversingId = null;
        $this->reverseReason = '';
    }

    public function reverse(StockLedger $ledger): void
    {
        $this->authorize('stock.reverse');
        $this->validate(['reverseReason' => 'required|min:5']);

        $tx = app(StockQueryService::class)->transactionWithLines($this->reversingId);
        abort_if($tx === null, 404);

        try {
            $reversal = $ledger->reverse($tx, $this->reverseReason);
            $this->dispatch('toast', message: "Reversed: {$reversal->number}", type: 'success');
            $this->reversingId = null;
            $this->reverseReason = '';
            unset($this->transactions);
        } catch (\Exception $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    #[Computed]
    public function transactions()
    {
        return app(StockQueryService::class)->transactionsPage(
            dateFrom: $this->dateFrom ?: null,
            dateTo: $this->dateTo ?: null,
            movementType: $this->filterType ?: null,
            referenceNo: $this->filterRefNo ?: null,
            postedBy: $this->filterUser ? (int) $this->filterUser : null,
            virtualWarehouseId: $this->filterVw ? (int) $this->filterVw : null,
            itemId: $this->filterItem ? (int) $this->filterItem : null,
            locationId: $this->filterLocation ? (int) $this->filterLocation : null,
            scopedUser: auth()->user(),
            perPage: 25,
        );
    }

    #[Computed]
    public function viewingTransaction()
    {
        if (! $this->viewingTransactionId) {
            return null;
        }

        return app(StockQueryService::class)->transactionWithLines(
            $this->viewingTransactionId,
            auth()->user()
        );
    }

    #[Computed]
    public function movementTypes(): array
    {
        return collect(MovementType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }

    #[Computed]
    public function virtualWarehouses()
    {
        $user = auth()->user();

        return VirtualWarehouse::active()
            ->when(! $user->canAccessAllVirtualWarehouses(), fn ($q) =>
                $q->whereHas('users', fn ($uq) => $uq->where('users.id', $user->id))
            )
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('livewire.inventory.stock.movements')
            ->layout('components.layouts.app', [
                'title'           => 'Movement History',
                'breadcrumbGroup' => 'Inventory Control',
            ]);
    }
}
