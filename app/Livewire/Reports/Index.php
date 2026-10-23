<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Jobs\GenerateReportJob;
use App\Models\Department;
use App\Models\ReportRequest;
use App\Models\VirtualWarehouse;
use App\Services\Reports\ReportRegistry;
use App\Services\Reports\ReportSummaryService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $reportKey = '';

    #[Url(except: '')]
    public string $dateFrom = '';

    #[Url(except: '')]
    public string $dateTo = '';

    #[Url(except: '')]
    public string $asOfDate = '';

    #[Url(except: '')]
    public string $warehouseId = '';

    #[Url(except: '')]
    public string $departmentId = '';

    // Activity report extras
    public string $docType   = 'all';
    public string $direction = 'both';
    public string $grouping  = 'day';

    public bool   $hasResults    = false;
    public bool   $showSummary   = false;
    public string $aiSummaryText = '';

    public array $trackedPendingIds = [];

    public function mount(): void
    {
        if ($this->dateFrom === '') {
            $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        }
        if ($this->dateTo === '') {
            $this->dateTo = now()->format('Y-m-d');
        }
        if ($this->asOfDate === '') {
            $this->asOfDate = now()->format('Y-m-d');
        }

        $this->trackedPendingIds = $this->pendingExportIds();
    }

    public function pollExports(): void
    {
        $previouslyPending = $this->trackedPendingIds;

        unset($this->recentRequests);

        $newlyReady = $this->recentRequests
            ->filter(fn ($r) => $r->isReady() && in_array($r->id, $previouslyPending))
            ->isNotEmpty();

        $this->trackedPendingIds = $this->pendingExportIds();

        if ($newlyReady) {
            $this->js('playReportReadySound()');
            $this->dispatch('toast', message: 'Export is ready — click Download.', type: 'success');
        }
    }

    private function pendingExportIds(): array
    {
        return ReportRequest::where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'processing'])
            ->pluck('id')
            ->toArray();
    }

    public function updatedReportKey(): void
    {
        $this->hasResults    = false;
        $this->aiSummaryText = '';
        $this->showSummary   = false;
        $this->resetPage();
    }

    #[Computed]
    public function registry(): ReportRegistry
    {
        return app(ReportRegistry::class);
    }

    #[Computed]
    public function currentDefinition(): ?\App\Contracts\Reports\ReportDefinition
    {
        if ($this->reportKey === '') {
            return null;
        }

        return $this->registry->find($this->reportKey);
    }

    #[Computed]
    public function results(): ?\Illuminate\Pagination\LengthAwarePaginator
    {
        if (! $this->hasResults || $this->currentDefinition === null) {
            return null;
        }

        return $this->currentDefinition->query($this->filters(), auth()->user())->paginate(25);
    }

    #[Computed]
    public function virtualWarehouses()
    {
        return VirtualWarehouse::active()
            ->accessibleBy(auth()->user())
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function departments()
    {
        $user = auth()->user();

        if ($user->canAccessAllVirtualWarehouses()) {
            return Department::where('is_active', true)->orderBy('name')->get();
        }

        $deptIds = VirtualWarehouse::query()
            ->accessibleBy($user)
            ->pluck('department_id')
            ->unique();

        return Department::whereIn('id', $deptIds)->orderBy('name')->get();
    }

    #[Computed]
    public function recentRequests()
    {
        return ReportRequest::where('user_id', auth()->id())
            ->whereNotNull('filters')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    public function generate(): void
    {
        if ($this->currentDefinition === null) {
            $this->dispatch('toast', message: 'Select a report first.', type: 'error');
            return;
        }

        $this->hasResults    = true;
        $this->aiSummaryText = '';
        $this->showSummary   = false;
        $this->resetPage();
        unset($this->results);
    }

    public function exportExcel(): void
    {
        $this->doExport('excel');
    }

    public function exportPdf(): void
    {
        $this->doExport('pdf');
    }

    private function doExport(string $format): void
    {
        $def = $this->currentDefinition;

        if ($def === null || ! $this->hasResults) {
            $this->dispatch('toast', message: 'Generate the report first.', type: 'error');
            return;
        }

        if ($format === 'pdf' && ! $def->pdfEnabled()) {
            $this->dispatch('toast', message: 'PDF export is not available for this report.', type: 'error');
            return;
        }

        $filters  = $this->filters();

        $req = ReportRequest::create([
            'user_id'   => auth()->id(),
            'type'      => $def->key(),
            'date_from' => $filters['date_from'] ?? $filters['as_of_date'] ?? null,
            'date_to'   => $filters['date_to']   ?? $filters['as_of_date'] ?? null,
            'filters'   => $filters,
            'format'    => $format,
            'status'    => 'pending',
        ]);

        GenerateReportJob::dispatch($req)->onQueue('default');

        $this->dispatch('toast', message: 'Export queued  you\'ll be notified when it\'s ready.', type: 'info');

        unset($this->recentRequests);
    }

    public function loadAiSummary(): void
    {
        $def = $this->currentDefinition;

        if ($def === null || ! config('ai.enabled', false)) {
            $this->dispatch('toast', message: 'AI Summary is not available.', type: 'error');
            return;
        }

        try {
            $this->aiSummaryText = app(ReportSummaryService::class)->getSummary(
                $def->key(),
                $this->filters(),
                auth()->user(),
            );
        } catch (\Throwable) {
            $this->aiSummaryText = '';
        }

        $this->showSummary = true;
    }

    public function filters(): array
    {
        $def  = $this->currentDefinition;
        $base = [
            'warehouse_id'  => $this->warehouseId  !== '' ? (int) $this->warehouseId  : null,
            'department_id' => $this->departmentId !== '' ? (int) $this->departmentId : null,
        ];

        if ($def?->periodType() === 'as_of') {
            $base['as_of_date'] = $this->asOfDate ?: now()->format('Y-m-d');
        } else {
            $base['date_from'] = $this->dateFrom ?: now()->startOfMonth()->format('Y-m-d');
            $base['date_to']   = $this->dateTo   ?: now()->format('Y-m-d');
        }

        if ($def?->key() === 'received-dispatched') {
            $base['doc_type']  = $this->docType;
            $base['direction'] = $this->direction;
            $base['grouping']  = $this->grouping;
        }

        return $base;
    }

    public function render(): View
    {
        return view('livewire.reports.index')
            ->layout('components.layouts.app', [
                'title'           => 'Reports',
                'breadcrumbGroup' => 'Reports',
            ]);
    }
}
