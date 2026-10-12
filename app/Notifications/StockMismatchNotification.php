<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class StockMismatchNotification extends Notification
{
    public function __construct(private readonly int $mismatchCount) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title'           => 'Stock Balance Mismatch Detected',
            'message'         => "{$this->mismatchCount} stock balance row(s) do not match the movement ledger.",
            'mismatch_count'  => $this->mismatchCount,
            'detected_at'     => now()->toIso8601String(),
        ];
    }
}
