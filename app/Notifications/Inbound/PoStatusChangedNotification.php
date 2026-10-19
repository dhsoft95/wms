<?php

declare(strict_types=1);

namespace App\Notifications\Inbound;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic PO status change notification.
 * Used for: returned, rejected, accepted, assigned, partial receipt,
 *           pending validation, completed, issue raised/resolved, verified/closed.
 */
class PoStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PurchaseOrder $po,
        public readonly string $event,   // e.g. 'returned', 'rejected', 'accepted'
        public readonly string $message,
        public readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("PO {$this->po->number}: {$this->message}")
            ->line($this->message);

        if ($this->reason) {
            $mail->line("Reason: {$this->reason}");
        }

        return $mail->action('View Purchase Order', route('inbound.orders.show', $this->po->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'      => 'po_status_changed',
            'event'     => $this->event,
            'po_id'     => $this->po->id,
            'po_number' => $this->po->number,
            'message'   => $this->message,
            'reason'    => $this->reason,
        ];
    }
}
