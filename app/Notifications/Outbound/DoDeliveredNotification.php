<?php

declare(strict_types=1);

namespace App\Notifications\Outbound;

use App\Models\DeliveryOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DoDeliveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly DeliveryOrder $deliveryOrder) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Delivery Order {$this->deliveryOrder->number} Delivered")
            ->line("Delivery Order {$this->deliveryOrder->number} has been marked as delivered with proof of delivery.")
            ->action('View Order', url("/outbound/orders/{$this->deliveryOrder->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'do_delivered',
            'do_id'   => $this->deliveryOrder->id,
            'do_no'   => $this->deliveryOrder->number,
            'message' => "Delivery Order {$this->deliveryOrder->number} marked as delivered.",
        ];
    }
}
