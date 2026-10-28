<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateReportJob;
use App\Models\ReportRequest;
use App\Models\ScheduledReport;
use App\Services\Reports\ReportRegistry;
use Illuminate\Console\Command;

class RunScheduledReports extends Command
{
    protected $signature   = 'reports:run-scheduled';
    protected $description = 'Dispatch queued jobs for all due scheduled reports';

    public function handle(ReportRegistry $registry): int
    {
        $due = ScheduledReport::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                  ->orWhere('next_run_at', '<=', now());
            })
            ->with('createdBy')
            ->get();

        if ($due->isEmpty()) {
            $this->info('No scheduled reports are due.');
            return self::SUCCESS;
        }

        foreach ($due as $schedule) {
            $definition = $registry->find($schedule->report_key);

            if (! $definition) {
                $this->warn("Report key '{$schedule->report_key}' not found in registry — skipping '{$schedule->name}'.");
                continue;
            }

            $user = $schedule->createdBy;

            if (! $user) {
                $this->warn("No owner user for schedule '{$schedule->name}' — skipping.");
                continue;
            }

            $periodType = $definition->periodType();
            $filters    = $schedule->resolveFilters($periodType);

            $request = ReportRequest::create([
                'user_id'              => $user->id,
                'scheduled_report_id'  => $schedule->id,
                'type'                 => $definition->key(),
                'date_from'            => $filters['date_from'] ?? $filters['as_of_date'] ?? null,
                'date_to'              => $filters['date_to']   ?? $filters['as_of_date'] ?? null,
                'status'               => 'pending',
                'format'               => $schedule->format,
                'filters'              => $filters,
            ]);

            GenerateReportJob::dispatch($request);

            $schedule->update([
                'last_run_at' => now(),
                'next_run_at' => $schedule->computeNextRunAt(),
            ]);

            $this->info("Dispatched: {$schedule->name} ({$definition->label()})");
        }

        return self::SUCCESS;
    }
}
