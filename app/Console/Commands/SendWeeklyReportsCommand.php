<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendWeeklyReportEmailJob;
use App\Models\Department;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendWeeklyReportsCommand extends Command
{
    protected $signature   = 'reports:weekly-email {--week= : ISO week start date (Y-m-d), defaults to last Monday}';
    protected $description = 'Send weekly report email to each department head';

    public function handle(): int
    {
        $weekStart = $this->option('week')
            ? Carbon::parse($this->option('week'))->startOfWeek()->format('Y-m-d')
            : Carbon::now()->subWeek()->startOfWeek()->format('Y-m-d');

        $departments = Department::where('is_active', true)
            ->where(fn ($q) => $q->whereNotNull('head_user_id')->orWhereNotNull('report_email'))
            ->with('head')
            ->get();

        $dispatched = 0;

        foreach ($departments as $department) {
            $recipients = $this->resolveRecipients($department);

            foreach ($recipients as [$email, $userId]) {
                SendWeeklyReportEmailJob::dispatch(
                    departmentId:    $department->id,
                    recipientEmail:  $email,
                    recipientUserId: $userId,
                    weekStart:       $weekStart,
                )->onQueue('emails');

                $dispatched++;
            }
        }

        $this->info("Queued {$dispatched} weekly report email(s) for week commencing {$weekStart}.");

        return self::SUCCESS;
    }

    private function resolveRecipients(Department $department): array
    {
        $recipients = [];

        if ($department->head_user_id && $department->head) {
            $recipients[] = [$department->head->email, $department->head_user_id];
        }

        if ($department->report_email && $department->report_email !== $department->head?->email) {
            $fallbackUserId = $department->head_user_id
                ?? User::role('warehouse-manager')->first()?->id
                ?? User::first()?->id;

            if ($fallbackUserId) {
                $recipients[] = [$department->report_email, $fallbackUserId];
            }
        }

        return $recipients;
    }
}
