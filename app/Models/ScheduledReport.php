<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduledReport extends Model
{
    protected $fillable = [
        'name',
        'report_key',
        'frequency',
        'send_day',
        'send_hour',
        'date_range',
        'format',
        'warehouse_id',
        'department_id',
        'recipients',
        'is_active',
        'last_run_at',
        'next_run_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'recipients'  => 'array',
            'is_active'   => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'send_day'    => 'integer',
            'send_hour'   => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reportRequests(): HasMany
    {
        return $this->hasMany(ReportRequest::class, 'scheduled_report_id');
    }

    // ─── Business Logic ───────────────────────────────────────────────────────

    /**
     * Compute the date/warehouse/department filters for this schedule's date range.
     * Pass the report's periodType ('range' or 'as_of') so the right keys are used.
     */
    public function resolveFilters(string $periodType = 'range'): array
    {
        $now = now();

        if ($periodType === 'as_of') {
            $asOf = match ($this->date_range) {
                'yesterday'     => $now->copy()->subDay()->format('Y-m-d'),
                'last_week'     => $now->copy()->subWeek()->endOfWeek()->format('Y-m-d'),
                'last_month'    => $now->copy()->subMonth()->endOfMonth()->format('Y-m-d'),
                default         => $now->format('Y-m-d'), // today
            };

            return array_filter([
                'as_of_date'    => $asOf,
                'warehouse_id'  => $this->warehouse_id,
                'department_id' => $this->department_id,
            ], fn ($v) => $v !== null);
        }

        [$dateFrom, $dateTo] = match ($this->date_range) {
            'last_7_days'  => [$now->copy()->subDays(6)->format('Y-m-d'),                    $now->format('Y-m-d')],
            'last_30_days' => [$now->copy()->subDays(29)->format('Y-m-d'),                   $now->format('Y-m-d')],
            'last_week'    => [$now->copy()->subWeek()->startOfWeek()->format('Y-m-d'),       $now->copy()->subWeek()->endOfWeek()->format('Y-m-d')],
            'last_month'   => [$now->copy()->subMonth()->startOfMonth()->format('Y-m-d'),     $now->copy()->subMonth()->endOfMonth()->format('Y-m-d')],
            'this_year'    => [$now->copy()->startOfYear()->format('Y-m-d'),                  $now->format('Y-m-d')],
            default        => [$now->copy()->startOfMonth()->format('Y-m-d'),                 $now->format('Y-m-d')], // this_month
        };

        return array_filter([
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'warehouse_id'  => $this->warehouse_id,
            'department_id' => $this->department_id,
        ], fn ($v) => $v !== null);
    }

    /**
     * Compute when this schedule should next run after the current time.
     */
    public function computeNextRunAt(): Carbon
    {
        $hour = (int) $this->send_hour;

        if ($this->frequency === 'daily') {
            $candidate = now()->setTime($hour, 0, 0);
            return $candidate->isPast() ? $candidate->addDay() : $candidate;
        }

        if ($this->frequency === 'weekly') {
            $day = max(1, min(7, (int) ($this->send_day ?? 1)));
            $candidate = now()->setTime($hour, 0, 0);
            // Advance until we hit the right day-of-week (ISO: 1=Mon…7=Sun)
            while ($candidate->dayOfWeekIso !== $day || $candidate->isPast()) {
                $candidate->addDay();
            }
            return $candidate;
        }

        // monthly
        $day = max(1, min(28, (int) ($this->send_day ?? 1)));
        $candidate = now()->setDay($day)->setTime($hour, 0, 0);
        if ($candidate->isPast()) {
            $candidate->addMonth()->setDay($day);
        }
        return $candidate;
    }

    // ─── Display Helpers ─────────────────────────────────────────────────────

    public static function frequencyOptions(): array
    {
        return ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
    }

    public static function dateRangeOptions(): array
    {
        return [
            'today'        => 'Today (as-of)',
            'yesterday'    => 'Yesterday (as-of)',
            'last_7_days'  => 'Last 7 days',
            'last_30_days' => 'Last 30 days',
            'last_week'    => 'Last week',
            'last_month'   => 'Last month',
            'this_month'   => 'This month (to date)',
            'this_year'    => 'This year (to date)',
        ];
    }

    public function frequencyLabel(): string
    {
        return match ($this->frequency) {
            'daily'   => 'Daily at ' . str_pad((string) $this->send_hour, 2, '0', STR_PAD_LEFT) . ':00',
            'weekly'  => 'Weekly · ' . ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$this->send_day ?? 1]
                         . ' at ' . str_pad((string) $this->send_hour, 2, '0', STR_PAD_LEFT) . ':00',
            'monthly' => 'Monthly · day ' . ($this->send_day ?? 1)
                         . ' at ' . str_pad((string) $this->send_hour, 2, '0', STR_PAD_LEFT) . ':00',
            default   => $this->frequency,
        };
    }
}
