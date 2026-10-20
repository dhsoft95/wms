<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\WeeklyReportMail;
use App\Models\Department;
use App\Models\User;
use App\Services\Reports\ReportSummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendWeeklyReportEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(
        public readonly int    $departmentId,
        public readonly string $recipientEmail,
        public readonly int    $recipientUserId,
        public readonly string $weekStart,
    ) {}

    public function handle(ReportSummaryService $summaryService): void
    {
        $department = Department::findOrFail($this->departmentId);
        $user       = User::findOrFail($this->recipientUserId);

        $filters = [
            'date_from'   => $this->weekStart,
            'date_to'     => \Carbon\Carbon::parse($this->weekStart)->addDays(6)->format('Y-m-d'),
        ];

        $reportTypes = [
            'stock-summary', 'inbound', 'outbound',
            'expiry-ageing', 'yard', 'adjustments-counts',
        ];

        $summaries = [];
        foreach ($reportTypes as $type) {
            $summaries[$type] = $summaryService->getSummary(
                reportType: $type,
                filters:    $filters,
                user:       $user,
                language:   \App\Services\Foundation\SettingsService::get('ai.language', 'en'),
            );
        }

        Mail::to($this->recipientEmail)->send(new WeeklyReportMail(
            department: $department,
            user:       $user,
            weekStart:  $this->weekStart,
            summaries:  $summaries,
            filters:    $filters,
        ));
    }

    public function uniqueId(): string
    {
        return "weekly-report-{$this->departmentId}-{$this->weekStart}";
    }
}
