<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Dashboard\DashboardMetrics;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Lazy]
class Overview extends Component
{
    #[Url]
    public ?int $warehouseId = null;

    #[Url]
    public int $days = 30;

    public function placeholder(): View
    {
        $role       = auth()->user()?->roleSlug() ?? 'viewer-auditor';
        $visibility = config("dashboard.kpi_visibility.{$role}", []);
        $allKeys    = ['stock_on_hand', 'open_orders', 'pending_approvals', 'to_receive',
                       'awaiting_putaway', 'to_pick', 'in_transit', 'low_stock', 'expiring', 'containers_in_yard'];
        $count = $visibility === 'all' ? count($allKeys) : count((array) $visibility);

        return view('livewire.dashboard.overview-placeholder', ['kpiCount' => $count]);
    }

    private function metrics(): DashboardMetrics
    {
        return app(DashboardMetrics::class);
    }

    private function user()
    {
        return auth()->user();
    }

    // ── Visibility helpers ───────────────────────────────────────────────────

    public function canSeeKpi(string $key): bool
    {
        $role       = $this->user()->roleSlug();
        $visibility = config("dashboard.kpi_visibility.{$role}", []);

        return $visibility === 'all' || in_array($key, (array) $visibility, true);
    }

    public function canSeeAction(string $key): bool
    {
        $role       = $this->user()->roleSlug();
        $visibility = config("dashboard.action_visibility.{$role}", []);

        return $visibility === 'all' || in_array($key, (array) $visibility, true);
    }

    public function isReadonly(): bool
    {
        return in_array($this->user()->roleSlug(), config('dashboard.readonly_roles', []), true);
    }

    public function canSeeChart(string $key): bool
    {
        $role       = $this->user()->roleSlug();
        $visibility = config("dashboard.chart_visibility.{$role}", []);

        return $visibility === 'all' || in_array($key, (array) $visibility, true);
    }

    // ── KPI computed properties ──────────────────────────────────────────────

    #[Computed]
    public function stockOnHand(): array
    {
        return $this->metrics()->stockOnHand($this->user(), $this->warehouseId);
    }

    #[Computed]
    public function openOrders(): array
    {
        return $this->metrics()->openOrders($this->user(), $this->warehouseId);
    }

    #[Computed]
    public function pendingApprovals(): array
    {
        return $this->metrics()->pendingApprovals($this->user());
    }

    #[Computed]
    public function toReceive(): array
    {
        return $this->metrics()->toReceive($this->user(), $this->warehouseId);
    }

    #[Computed]
    public function awaitingPutaway(): array
    {
        return $this->metrics()->awaitingPutaway($this->user(), $this->warehouseId);
    }

    #[Computed]
    public function toPick(): array
    {
        return $this->metrics()->toPick($this->user(), $this->warehouseId);
    }

    #[Computed]
    public function inTransit(): array
    {
        return $this->metrics()->inTransit($this->user(), $this->warehouseId);
    }

    #[Computed]
    public function lowStock(): array
    {
        return $this->metrics()->lowStock($this->user(), $this->warehouseId);
    }

    #[Computed]
    public function expiring(): array
    {
        return $this->metrics()->expiring($this->user(), $this->warehouseId);
    }

    #[Computed]
    public function containersInYard(): array
    {
        return $this->metrics()->containersInYard($this->user());
    }

    // ── Sparkline computed properties ────────────────────────────────────────

    #[Computed]
    public function sparkStockReceived(): array
    {
        return $this->metrics()->sparkStockReceived($this->user(), $this->warehouseId);
    }

    #[Computed]
    public function sparkOrdersDispatched(): array
    {
        return $this->metrics()->sparkOrdersDispatched($this->user(), $this->warehouseId);
    }

    // ── KPI card definitions ─────────────────────────────────────────────────

    #[Computed]
    public function kpis(): array
    {
        $stockSpark = $this->sparkStockReceived;
        $pickSpark  = $this->sparkOrdersDispatched;

        $all = [];

        if ($this->canSeeKpi('stock_on_hand')) {
            $all[] = [
                'label' => 'Stock on Hand',
                'value' => number_format($this->stockOnHand['qty'], 0),
                'hint'  => number_format($this->stockOnHand['sku_count']) . ' SKUs',
                'icon'  => 'archive',
                'tone'  => 'brand',
                'href'  => route('inventory.stock.index'),
                'spark' => $stockSpark,
                'trend' => $this->computeTrend($stockSpark),
            ];
        }

        if ($this->canSeeKpi('open_orders')) {
            $all[] = [
                'label' => 'Open Orders',
                'value' => number_format($this->openOrders['total']),
                'hint'  => $this->openOrders['po_count'] . ' PO · ' . $this->openOrders['mrn_count'] . ' MRN · ' . $this->openOrders['transfer_count'] . ' STN',
                'icon'  => 'clipboard-list',
                'tone'  => 'brand',
                'href'  => route('outbound.orders.index'),
                'spark' => null,
                'trend' => null,
            ];
        }

        if ($this->canSeeKpi('pending_approvals')) {
            $all[] = [
                'label' => 'Pending Approvals',
                'value' => (string) $this->pendingApprovals['total'],
                'hint'  => 'For you to action',
                'icon'  => 'bell',
                'tone'  => $this->pendingApprovals['total'] > 0 ? 'warning' : 'neutral',
                'href'  => route('approvals.index'),
                'spark' => null,
                'trend' => null,
            ];
        }

        if ($this->canSeeKpi('to_receive')) {
            $all[] = [
                'label' => 'To Receive',
                'value' => (string) $this->toReceive['total'],
                'hint'  => $this->toReceive['today'] . ' started today',
                'icon'  => 'inbox',
                'tone'  => 'brand',
                'href'  => route('inbound.grn.index'),
                'spark' => null,
                'trend' => null,
            ];
        }

        if ($this->canSeeKpi('awaiting_putaway')) {
            $all[] = [
                'label' => 'Awaiting Put-Away',
                'value' => (string) $this->awaitingPutaway['total'],
                'hint'  => 'Open tasks',
                'icon'  => 'arrows',
                'tone'  => 'brand',
                'href'  => route('inbound.putaway.index'),
                'spark' => null,
                'trend' => null,
            ];
        }

        if ($this->canSeeKpi('to_pick')) {
            $all[] = [
                'label' => 'To Pick / Dispatch',
                'value' => (string) $this->toPick['total'],
                'hint'  => 'Orders ready to pick',
                'icon'  => 'truck',
                'tone'  => 'success',
                'href'  => route('outbound.orders.index'),
                'spark' => $pickSpark,
                'trend' => $this->computeTrend($pickSpark),
            ];
        }

        if ($this->canSeeKpi('in_transit')) {
            $all[] = [
                'label' => 'In Transit',
                'value' => number_format($this->inTransit['total']),
                'hint'  => $this->inTransit['transfers'] . ' transfers',
                'icon'  => 'cube',
                'tone'  => 'brand',
                'href'  => route('warehouse.wtr.index'),
                'spark' => null,
                'trend' => null,
            ];
        }

        if ($this->canSeeKpi('low_stock')) {
            $all[] = [
                'label' => 'Low Stock',
                'value' => (string) $this->lowStock['count'],
                'hint'  => 'SKUs below min stock',
                'icon'  => 'alert',
                'tone'  => $this->lowStock['count'] > 0 ? 'danger' : 'neutral',
                'href'  => route('inventory.stock.index'),
                'spark' => null,
                'trend' => null,
            ];
        }

        if ($this->canSeeKpi('expiring')) {
            $all[] = [
                'label' => 'Expiring Soon',
                'value' => (string) $this->expiring['count'],
                'hint'  => 'SKUs expiring in ≤ 30 days',
                'icon'  => 'clock',
                'tone'  => $this->expiring['count'] > 0 ? 'warning' : 'neutral',
                'href'  => route('inventory.stock.index'),
                'spark' => null,
                'trend' => null,
            ];
        }

        if ($this->canSeeKpi('containers_in_yard')) {
            $overDwell = $this->containersInYard['over_dwell'];
            $all[] = [
                'label' => 'Containers in Yard',
                'value' => (string) $this->containersInYard['total'],
                'hint'  => $overDwell > 0 ? $overDwell . ' over dwell limit' : 'All within dwell limit',
                'icon'  => 'cube',
                'tone'  => 'brand',
                'href'  => route('inbound.containers.index'),
                'spark' => null,
                'trend' => null,
            ];
        }

        return $all;
    }

    private function computeTrend(array $spark): ?array
    {
        if (count($spark) < 6) {
            return null;
        }

        $recent   = array_sum(array_slice($spark, -3));
        $previous = array_sum(array_slice($spark, -6, 3));

        if ($previous == 0) {
            return null;
        }

        $percent = (int) round((($recent - $previous) / $previous) * 100);

        return [
            'direction' => match (true) {
                $percent > 0 => 'up',
                $percent < 0 => 'down',
                default      => 'flat',
            },
            'percent' => abs($percent),
        ];
    }

    // ── Action list computed properties ──────────────────────────────────────

    #[Computed]
    public function myApprovals(): Collection
    {
        return $this->metrics()->myApprovals($this->user());
    }

    #[Computed]
    public function overdueOrders(): Collection
    {
        return $this->metrics()->overdueOrders($this->user(), $this->warehouseId);
    }

    #[Computed]
    public function recentVariances(): Collection
    {
        return $this->metrics()->recentVariances($this->user(), $this->warehouseId);
    }

    #[Computed]
    public function recentActivity(): Collection
    {
        return $this->metrics()->recentActivity($this->user(), $this->warehouseId);
    }

    // ── Warehouse list for the filter dropdown ────────────────────────────────

    #[Computed]
    public function warehouses(): Collection
    {
        return \App\Models\Warehouse::active()->orderBy('name')->get(['id', 'name']);
    }

    // ── Chart computed properties ─────────────────────────────────────────────

    #[Computed]
    public function chartInboundVsOutbound(): array
    {
        return $this->metrics()->chartInboundVsOutbound($this->user(), $this->days);
    }

    #[Computed]
    public function chartOrderPipeline(): array
    {
        return $this->metrics()->chartOrderStatusPipeline($this->user());
    }

    #[Computed]
    public function chartBinUtilisation(): array
    {
        return $this->metrics()->chartBinUtilisation($this->user(), $this->warehouseId);
    }

    #[Computed]
    public function chartTopMovingItems(): array
    {
        return $this->metrics()->chartTopMovingItems($this->user(), $this->days);
    }

    public function render(): View
    {
        return view('livewire.dashboard.overview')
            ->layout('components.layouts.app', [
                'title'           => 'Operations Overview',
                'breadcrumbGroup' => 'Overview',
            ]);
    }
}
