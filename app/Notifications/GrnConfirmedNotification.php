<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Grn;
use Illuminate\Notifications\Notification;

final class GrnConfirmedNotification extends Notification
{
    public function __construct(public readonly Grn $grn) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'grn_id'    => $this->grn->id,
            'grn_number'=> $this->grn->number,
            'status'    => $this->grn->status->value,
            'message'   => "GRN {$this->grn->number} has been confirmed.",
        ];
    }
}
