<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ReportRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ReportReadyNotification extends Notification
{
    public function __construct(public readonly ReportRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'report_request_id' => $this->request->id,
            'report_type'       => $this->request->type,
            'format'            => $this->request->format ?? 'excel',
            'row_count'         => $this->request->row_count,
            'download_url'      => route('reports.download', $this->request),
            'message'           => "Your {$this->request->type} report ({$this->request->row_count} rows) is ready to download.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your report is ready — ' . $this->request->type)
            ->line("Your export ({$this->request->row_count} rows) has finished processing.")
            ->action('Download Report', route('reports.download', $this->request))
            ->line('This link is valid for 24 hours.');
    }
}
