<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Department;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Department $department,
        public readonly User       $user,
        public readonly string     $weekStart,
        public readonly array      $summaries,
        public readonly array      $filters,
    ) {}

    public function envelope(): Envelope
    {
        $weekLabel = \Carbon\Carbon::parse($this->weekStart)->format('d M Y');

        return new Envelope(
            subject: "Weekly Warehouse Report — {$this->department->name} — w/c {$weekLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.weekly-report',
        );
    }
}
