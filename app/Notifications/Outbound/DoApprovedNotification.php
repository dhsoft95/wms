<?php

declare(strict_types=1);

namespace App\Notifications\Outbound;

use App\Models\DeliveryOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DoApprovedNotification extends Notification implements ShouldQueue
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
            ->subject("Delivery Order {$this->deliveryOrder->number} Approved")
            ->line("Your Delivery Order {$this->deliveryOrder->number} has been approved and is ready for stock allocation.")
            ->action('View Order', url("/outbound/orders/{$this->deliveryOrder->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'do_approved',
            'do_id'   => $this->deliveryOrder->id,
            'do_no'   => $this->deliveryOrder->number,
            'message' => "Delivery Order {$this->deliveryOrder->number} approved.",
        ];
    }
}
