<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CategoryApprovalNeeded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $documentType,
        public readonly int    $documentId,
        public readonly string $documentNumber,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Approval Required: {$this->documentNumber}")
            ->line("A {$this->documentType} ({$this->documentNumber}) requires your approval.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'document_type'   => $this->documentType,
            'document_id'     => $this->documentId,
            'document_number' => $this->documentNumber,
        ];
    }
}
