<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\Reports\ReportSummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateReportSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(
        public readonly string $reportType,
        public readonly array  $filters,
        public readonly int    $userId,
        public readonly string $language = 'en',
        public readonly bool   $force = false,
    ) {}

    public function handle(ReportSummaryService $service): void
    {
        $user = User::findOrFail($this->userId);

        $service->getSummary(
            reportType: $this->reportType,
            filters:    $this->filters,
            user:       $user,
            language:   $this->language,
            force:      $this->force,
        );
    }
}
