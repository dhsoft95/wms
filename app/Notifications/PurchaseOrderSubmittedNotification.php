<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseOrderSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PurchaseOrder $po,
        public readonly User $submittedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("PO {$this->po->number} submitted for approval")
            ->line('A purchase order has been submitted and requires your approval.')
            ->action('View Purchase Order', route('inbound.orders.show', $this->po->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'po_submitted',
            'po_id'        => $this->po->id,
            'po_number'    => $this->po->number,
            'submitted_by' => $this->submittedBy->name,
        ];
    }
}
