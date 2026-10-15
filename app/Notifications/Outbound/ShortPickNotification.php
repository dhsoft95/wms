<?php

declare(strict_types=1);

namespace App\Notifications\Outbound;

use App\Models\PickTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShortPickNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly PickTask $task) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $item     = $this->task->item;
        $location = $this->task->location;

        return (new MailMessage)
            ->subject('Short Pick — Possible Stock Discrepancy')
            ->line("A short pick has been recorded for {$item->sku} ({$item->name}) at location {$location->code}.")
            ->line("Short qty: {$this->task->qty_short} — system stock may be incorrect.")
            ->line('A count request has been created for this location.')
            ->action('View Pick List', url("/outbound/pick-lists"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'short_pick',
            'task_id'     => $this->task->id,
            'item_id'     => $this->task->item_id,
            'item_sku'    => $this->task->item?->sku,
            'location_id' => $this->task->location_id,
            'location'    => $this->task->location?->code,
            'short_qty'   => $this->task->qty_short,
            'message'     => "Short pick: {$this->task->item?->sku} at {$this->task->location?->code} — qty short: {$this->task->qty_short}",
        ];
    }
}
