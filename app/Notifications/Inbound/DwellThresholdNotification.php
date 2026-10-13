<?php

declare(strict_types=1);

namespace App\Notifications\Inbound;

use App\Models\Container;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DwellThresholdNotification extends Notification
{
    public function __construct(
        private readonly Container $container,
        private readonly string    $threshold,  // 'warning' | 'critical'
        private readonly int       $dwellDays,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'         => 'dwell_threshold',
            'title'        => ucfirst($this->threshold) . ' Dwell Threshold Reached',
            'message'      => "Container {$this->container->container_no} has been in yard for {$this->dwellDays} days ({$this->threshold} threshold).",
            'container_id' => $this->container->id,
            'container_no' => $this->container->container_no,
            'threshold'    => $this->threshold,
            'dwell_days'   => $this->dwellDays,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(ucfirst($this->threshold) . " Dwell Alert: {$this->container->container_no}")
            ->line("Container {$this->container->container_no} has been in the yard for {$this->dwellDays} days.")
            ->line("This has reached the {$this->threshold} dwell threshold.")
            ->action('View Container', url(route('inbound.containers.show', $this->container)));
    }
}
