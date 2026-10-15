<?php

declare(strict_types=1);

namespace App\Notifications\Outbound;

use App\Models\DeliveryOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DoBackorderCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly DeliveryOrder $original,
        public readonly DeliveryOrder $backorder,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Backorder {$this->backorder->number} Created")
            ->line("A backorder {$this->backorder->number} has been created for the unallocated items from {$this->original->number}.")
            ->action('View Backorder', url("/outbound/orders/{$this->backorder->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'do_backorder_created',
            'original_id'  => $this->original->id,
            'original_no'  => $this->original->number,
            'backorder_id' => $this->backorder->id,
            'backorder_no' => $this->backorder->number,
            'message'      => "Backorder {$this->backorder->number} created from {$this->original->number}.",
        ];
    }
}
