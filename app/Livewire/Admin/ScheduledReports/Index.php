<?php

declare(strict_types=1);

namespace App\Livewire\Admin\ScheduledReports;

use App\Jobs\GenerateReportJob;
use App\Models\ReportRequest;
use App\Models\ScheduledReport;
use App\Services\Reports\ReportRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Index extends Component
{
    public bool   $showModal      = false;
    public ?int   $editingId      = null;
    public ?int   $deleteId       = null;
    public string $deleteConfirmName = '';
    public ?int   $viewId         = null;
    public string $viewScheduleName = '';

    // Form fields
    public string  $name           = '';
    public string  $report_key     = '';
    public string  $frequency      = 'daily';
    public ?int    $send_day       = null;
    public int     $send_hour      = 7;
    public string  $date_range     = 'last_7_days';
    public string  $format         = 'excel';
    public ?int    $warehouse_id   = null;
    public ?int    $department_id  = null;
    public string  $recipients_text = ''; // newline-separated emails
    public bool    $is_active      = true;

    protected function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:100'],
            'report_key'      => ['required', 'string'],
            'frequency'       => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'send_day'        => ['nullable', 'integer', 'min:1', 'max:28'],
            'send_hour'       => ['required', 'integer', 'min:0', 'max:23'],
            'date_range'      => ['required', 'string'],
            'format'          => ['required', Rule::in(['excel', 'pdf'])],
            'warehouse_id'    => ['nullable', 'integer'],
            'department_id'   => ['nullable', 'integer'],
            'recipients_text' => ['required', 'string'],
            'is_active'       => ['boolean'],
        ];
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function closeDeleteModal(): void
    {
        $this->deleteId = null;
    }

    public function showRuns(int $id): void
    {
        $schedule = ScheduledReport::findOrFail($id);
        $this->viewId           = $id;
        $this->viewScheduleName = $schedule->name;
    }

    public function closeRunsModal(): void
    {
        $this->viewId = null;
    }

    public function openCreate(): void
    {
        $this->reset(['name', 'report_key', 'frequency', 'send_day', 'send_hour',
                      'date_range', 'format', 'warehouse_id', 'department_id',
                      'recipients_text', 'is_active', 'editingId']);
        $this->send_hour    = 7;
        $this->frequency    = 'daily';
        $this->date_range   = 'last_7_days';
        $this->format       = 'excel';
        $this->is_active    = true;
        $this->showModal    = true;
        $this->resetErrorBag();
    }

    public function openEdit(int $id): void
    {
        $schedule = ScheduledReport::findOrFail($id);

        $this->editingId        = $id;
        $this->name             = $schedule->name;
        $this->report_key       = $schedule->report_key;
        $this->frequency        = $schedule->frequency;
        $this->send_day         = $schedule->send_day;
        $this->send_hour        = $schedule->send_hour;
        $this->date_range       = $schedule->date_range;
        $this->format           = $schedule->format;
        $this->warehouse_id     = $schedule->warehouse_id;
        $this->department_id    = $schedule->department_id;
        $this->recipients_text  = implode("\n", $schedule->recipients ?? []);
        $this->is_active        = $schedule->is_active;
        $this->showModal        = true;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->validate();

        $recipients = collect(preg_split('/[\s,]+/', trim($this->recipients_text)))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();

        if (empty($recipients)) {
            $this->addError('recipients_text', 'At least one valid email address is required.');
            return;
        }

        $data = [
            'name'           => $this->name,
            'report_key'     => $this->report_key,
            'frequency'      => $this->frequency,
            'send_day'       => in_array($this->frequency, ['weekly', 'monthly']) ? $this->send_day : null,
            'send_hour'      => $this->send_hour,
            'date_range'     => $this->date_range,
            'format'         => $this->format,
            'warehouse_id'   => $this->warehouse_id ?: null,
            'department_id'  => $this->department_id ?: null,
            'recipients'     => $recipients,
            'is_active'      => $this->is_active,
            'created_by'     => Auth::id(),
        ];

        if ($this->editingId) {
            $schedule = ScheduledReport::findOrFail($this->editingId);
            $schedule->update($data);
            // Recompute next run
            $schedule->update(['next_run_at' => $schedule->computeNextRunAt()]);
        } else {
            $schedule = ScheduledReport::create($data);
            $schedule->update(['next_run_at' => $schedule->computeNextRunAt()]);
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: 'Schedule saved.');
    }

    public function toggleActive(int $id): void
    {
        $schedule = ScheduledReport::findOrFail($id);
        $schedule->update(['is_active' => ! $schedule->is_active]);
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId          = $id;
        $this->deleteConfirmName = ScheduledReport::findOrFail($id)->name;
    }

    public function delete(): void
    {
        if ($this->deleteId) {
            ScheduledReport::findOrFail($this->deleteId)->delete();
            $this->deleteId = null;
            $this->dispatch('toast', type: 'success', message: 'Schedule deleted.');
        }
    }

    public function runNow(int $id): void
    {
        $schedule   = ScheduledReport::findOrFail($id);
        $registry   = app(ReportRegistry::class);
        $definition = $registry->find($schedule->report_key);

        if (! $definition) {
            $this->dispatch('toast', type: 'error', message: 'Report definition not found.');
            return;
        }

        $filters = $schedule->resolveFilters($definition->periodType());

        $request = ReportRequest::create([
            'user_id'             => Auth::id(),
            'scheduled_report_id' => $schedule->id,
            'type'                => $definition->key(),
            'date_from'           => $filters['date_from'] ?? $filters['as_of_date'] ?? null,
            'date_to'             => $filters['date_to']   ?? $filters['as_of_date'] ?? null,
            'status'              => 'pending',
            'format'              => $schedule->format,
            'filters'             => $filters,
        ]);

        GenerateReportJob::dispatch($request);

        $schedule->update(['last_run_at' => now()]);

        $this->dispatch('toast', type: 'success', message: 'Report queued — recipients will be emailed when ready.');
    }

    public function render(): \Illuminate\View\View
    {
        $schedules   = ScheduledReport::with('createdBy')->orderBy('name')->get();
        $registry    = app(ReportRegistry::class);
        $reportDefs  = $registry->grouped();
        $warehouses  = \App\Models\VirtualWarehouse::orderBy('name')->get();
        $departments = \App\Models\Department::orderBy('name')->get();
        $recentRuns  = $this->viewId
            ? \App\Models\ReportRequest::where('scheduled_report_id', $this->viewId)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
            : collect();

        return view('livewire.admin.scheduled-reports.index', compact(
            'schedules', 'reportDefs', 'warehouses', 'departments', 'recentRuns'
        ));
    }
}
