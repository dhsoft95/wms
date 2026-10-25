<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\Dashboard\DashboardMetrics;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class Overview extends Component
{
    #[Url]
    public ?int $warehouseId = null;

    #[Url]
    public int $days = 30;

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
