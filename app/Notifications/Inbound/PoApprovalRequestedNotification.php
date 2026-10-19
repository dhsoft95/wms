<?php

declare(strict_types=1);

namespace App\Notifications\Inbound;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PoApprovalRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PurchaseOrder $po,
        public readonly int $level,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Action required: PO {$this->po->number} awaiting your approval (level {$this->level})")
            ->line("Purchase order {$this->po->number} requires your approval at level {$this->level}.")
            ->action('Review Purchase Order', route('inbound.orders.show', $this->po->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'      => 'po_approval_requested',
            'po_id'     => $this->po->id,
            'po_number' => $this->po->number,
            'level'     => $this->level,
        ];
    }
}
