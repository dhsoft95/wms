<?php

declare(strict_types=1);

namespace App\Notifications\Inbound;

use App\Models\Container;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SealMismatchNotification extends Notification
{
    public function __construct(
        private readonly Container $container,
        private readonly string    $expectedSeal,
        private readonly string    $actualSeal,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'seal_mismatch',
            'title'         => 'Seal Mismatch Detected',
            'message'       => "Container {$this->container->container_no}: expected seal {$this->expectedSeal}, found {$this->actualSeal}.",
            'container_id'  => $this->container->id,
            'container_no'  => $this->container->container_no,
            'expected_seal' => $this->expectedSeal,
            'actual_seal'   => $this->actualSeal,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Seal Mismatch: {$this->container->container_no}")
            ->error()
            ->line("A seal mismatch was detected for container {$this->container->container_no}.")
            ->line("Expected seal: {$this->expectedSeal}")
            ->line("Arrival seal: {$this->actualSeal}")
            ->action('View Container', url(route('inbound.containers.show', $this->container)));
    }
}
