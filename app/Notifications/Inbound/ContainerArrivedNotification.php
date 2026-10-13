<?php

declare(strict_types=1);

namespace App\Notifications\Inbound;

use App\Models\Container;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ContainerArrivedNotification extends Notification
{
    public function __construct(private readonly Container $container) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'         => 'container_arrived',
            'title'        => 'Container Arrived',
            'message'      => "Container {$this->container->container_no} ({$this->container->number}) has arrived.",
            'container_id' => $this->container->id,
            'container_no' => $this->container->container_no,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Container Arrived: {$this->container->container_no}")
            ->line("Container {$this->container->container_no} ({$this->container->number}) has arrived.")
            ->line("BL: {$this->container->bl_no}  |  Vessel: {$this->container->vessel}")
            ->action('View Container', url(route('inbound.containers.show', $this->container)));
    }
}
