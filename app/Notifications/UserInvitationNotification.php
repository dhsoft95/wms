<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class UserInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $plainToken) {}

    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = URL::signedRoute('invitation.accept', [
            'user'  => $notifiable->id,
            'token' => $this->plainToken,
        ]);

        $expiry = config('wms.invitation_expiry_hours', 72);

        return (new MailMessage)
            ->subject('You have been invited to ' . config('app.name'))
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line("You have been invited to access the {$notifiable->roleName()} portal.")
            ->action('Accept Invitation & Set Password', $url)
            ->line("This link expires in {$expiry} hours.")
            ->line('If you did not expect this invitation, no action is needed.');
    }
}
