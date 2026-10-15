<?php

declare(strict_types=1);

namespace App\Notifications\Outbound;

use App\Models\DeliveryOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DoRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly DeliveryOrder $deliveryOrder,
        public readonly string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Delivery Order {$this->deliveryOrder->number} Rejected")
            ->line("Delivery Order {$this->deliveryOrder->number} has been rejected and returned to draft.")
            ->line("Reason: {$this->reason}")
            ->action('View Order', url("/outbound/orders/{$this->deliveryOrder->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'do_rejected',
            'do_id'   => $this->deliveryOrder->id,
            'do_no'   => $this->deliveryOrder->number,
            'reason'  => $this->reason,
            'message' => "Delivery Order {$this->deliveryOrder->number} rejected: {$this->reason}",
        ];
    }
}
