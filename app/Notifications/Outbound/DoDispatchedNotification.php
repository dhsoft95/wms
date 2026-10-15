<?php

declare(strict_types=1);

namespace App\Notifications\Outbound;

use App\Models\Dispatch;
use App\Models\DeliveryOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DoDispatchedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly DeliveryOrder $deliveryOrder,
        public readonly Dispatch $dispatch,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Delivery Order {$this->deliveryOrder->number} Dispatched")
            ->line("Delivery Order {$this->deliveryOrder->number} has been dispatched.")
            ->line("Dispatch Note: {$this->dispatch->number}")
            ->line("Vehicle: {$this->dispatch->vehicle_no} — Driver: {$this->dispatch->driver_name}")
            ->action('View Order', url("/outbound/orders/{$this->deliveryOrder->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'do_dispatched',
            'do_id'       => $this->deliveryOrder->id,
            'do_no'       => $this->deliveryOrder->number,
            'dispatch_no' => $this->dispatch->number,
            'message'     => "DO {$this->deliveryOrder->number} dispatched as {$this->dispatch->number}.",
        ];
    }
}
