<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SetPasswordInvitation extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $roleLabel,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.set', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expires = (int) config('auth.passwords.invitations.expire', 1440);

        return (new MailMessage)
            ->subject('Set your WorkZone password')
            ->greeting('Welcome to WorkZone')
            ->line("An administrator created a {$this->roleLabel} account for you.")
            ->line('Use the button below to choose your password and sign in.')
            ->action('Set password', $url)
            ->line("This one-time link expires in {$expires} minutes.");
    }
}
