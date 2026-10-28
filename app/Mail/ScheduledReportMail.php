<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ReportRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ScheduledReportMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly ReportRequest $reportRequest,
        public readonly string $scheduleName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Report] {$this->scheduleName}",
        );
    }

    public function content(): Content
    {
        $disk   = config('reports.disk', 'local');
        $format = strtoupper($this->reportRequest->format ?? 'excel');
        $rows   = $this->reportRequest->row_count;

        return new Content(
            markdown: 'emails.scheduled-report',
            with: [
                'scheduleName' => $this->scheduleName,
                'reportType'   => $this->reportRequest->type,
                'format'       => $format,
                'rows'         => $rows,
                'dateFrom'     => $this->reportRequest->date_from?->format('d M Y'),
                'dateTo'       => $this->reportRequest->date_to?->format('d M Y'),
                'generatedAt'  => $this->reportRequest->updated_at->format('d M Y H:i'),
            ],
        );
    }

    public function attachments(): array
    {
        $disk = config('reports.disk', 'local');
        $path = $this->reportRequest->file_path;

        if (! $path || ! Storage::disk($disk)->exists($path)) {
            return [];
        }

        $ext      = pathinfo($path, PATHINFO_EXTENSION);
        $mime     = $ext === 'pdf' ? 'application/pdf'
                  : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $filename = str($this->scheduleName)->slug() . '.' . $ext;

        return [
            Attachment::fromStorageDisk($disk, $path)
                ->as($filename)
                ->withMime($mime),
        ];
    }
}
