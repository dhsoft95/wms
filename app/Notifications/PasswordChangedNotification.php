<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your password was changed — ' . config('app.name'))
            ->greeting('Hi ' . $notifiable->name . ',')
            ->line('Your account password was just changed.')
            ->line('If you made this change, no further action is needed.')
            ->line('If you did not change your password, please contact your system administrator immediately.');
    }
}
