<?php

namespace App\Notifications;

use App\Services\Audit\AuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformPaymentMethodChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function __construct(
        private readonly array $old,
        private readonly array $new,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $oldId = AuditLogger::maskIdentifier((string) ($this->old['account_identifier'] ?? ''));
        $newId = AuditLogger::maskIdentifier((string) ($this->new['account_identifier'] ?? ''));

        return (new MailMessage)
            ->subject('Platform payment method changed')
            ->line('A platform payment method was updated.')
            ->line('Old identifier: '.($oldId ?: '—'))
            ->line('New identifier: '.($newId ?: '—'))
            ->line('Old holder: '.($this->old['account_holder'] ?? '—'))
            ->line('New holder: '.($this->new['account_holder'] ?? '—'))
            ->line('If you did not make this change, investigate immediately.');
    }
}
