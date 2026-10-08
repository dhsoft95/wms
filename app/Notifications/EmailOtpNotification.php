<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiry = config('wms.otp_expiry_minutes', 10);

        return (new MailMessage)
            ->subject('Your verification code — ' . config('app.name'))
            ->greeting('Hi ' . $notifiable->name . ',')
            ->line('Your one-time verification code is:')
            ->line("**{$this->code}**")
            ->line("This code expires in {$expiry} minutes.")
            ->line('Never share this code with anyone.');
    }
}
