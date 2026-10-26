<?php

declare(strict_types=1);

namespace App\Livewire\Inbound\Orders;

use App\Enums\Inbound\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search       = '';
    public string $statusFilter = '';

    public function updatedSearch(): void       { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        $user = auth()->user();

        $query = PurchaseOrder::with(['warehouse', 'supplierPartner', 'requestedBy'])
            ->withSum('lines as total_expected_qty', 'qty')
            ->withSum('grnLines as total_received_qty', 'received_good_qty');

        if ($user->hasRole('department-user')) {
            $query->where('status', '!=', PurchaseOrderStatus::ReturnedForCorrection->value);
            if ($user->department_id) {
                $query->where('department_id', $user->department_id);
            }
        }

        $safeStatusFilter = ($user->hasRole('department-user') &&
            $this->statusFilter === PurchaseOrderStatus::ReturnedForCorrection->value)
            ? '' : $this->statusFilter;

        return $query
            ->when($this->search, fn ($q) => $q->where('number', 'like', "%{$this->search}%")
                ->orWhere('customer_po_ref', 'like', "%{$this->search}%"))
            ->when($safeStatusFilter, fn ($q) => $q->where('status', $safeStatusFilter))
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    #[Computed]
    public function statusOptions(): array
    {
        return PurchaseOrderStatus::cases();
    }

    public function render(): View
    {
        return view('livewire.inbound.orders.index')
            ->layout('components.layouts.app', [
                'title'           => 'Purchase Orders',
                'breadcrumbGroup' => 'Inbound',
            ]);
    }
}
